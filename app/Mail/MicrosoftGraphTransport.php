<?php

namespace App\Mail;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use SensitiveParameter;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Envía los correos con Microsoft Graph desde un buzón de Microsoft 365.
 *
 * La app se identifica con OAuth (id de la app y un secreto creados en
 * Microsoft Entra), no con la contraseña del buzón: Microsoft está apagando
 * el envío por SMTP con contraseña. Ver docs/correo-microsoft-365.md.
 *
 * El correo se arma igual que para SMTP (HTML, texto, adjuntos y
 * encabezados) y se entrega completo a Graph en formato MIME.
 */
final class MicrosoftGraphTransport extends AbstractTransport
{
    /**
     * Graph no acepta peticiones de más de 4 MB, y el correo va en base64
     * (pesa un tercio más). Se deja un margen.
     */
    public const MAX_REQUEST_BYTES = 4_000_000;

    private const GRAPH_URL = 'https://graph.microsoft.com/v1.0/users/%s/sendMail';

    private const TOKEN_URL = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';

    /**
     * Permisos vigentes por app. Duran una hora; se guardan en memoria (no
     * en la base de datos) y se piden de nuevo cinco minutos antes.
     *
     * @var array<string, array{token: string, expires: int}>
     */
    private static array $tokens = [];

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        private readonly string $mailbox,
    ) {
        parent::__construct();
    }

    public function mailbox(): string
    {
        return $this->mailbox;
    }

    /**
     * Olvida los permisos guardados (pruebas, o después de cambiar el secreto).
     */
    public static function forgetTokens(): void
    {
        self::$tokens = [];
    }

    protected function doSend(SentMessage $message): void
    {
        $missing = array_keys(array_filter([
            'MICROSOFT_GRAPH_TENANT_ID' => $this->tenantId === '',
            'MICROSOFT_GRAPH_CLIENT_ID' => $this->clientId === '',
            'MICROSOFT_GRAPH_CLIENT_SECRET' => $this->clientSecret === '',
            'MAIL_FROM_ADDRESS' => $this->mailbox === '',
        ]));

        if ($missing !== []) {
            throw new TransportException('Falta configurar en el .env: '.implode(', ', $missing).'.');
        }

        $mime = base64_encode($message->toString());

        if (strlen($mime) > self::MAX_REQUEST_BYTES) {
            throw new TransportException(sprintf(
                'El correo pesa %.1f MB y Microsoft Graph acepta hasta 4 MB. Baja PQRS_MAIL_MAX_ATTACHMENTS_KB para que los adjuntos se descarguen desde la consulta.',
                strlen($mime) / 1_000_000,
            ));
        }

        $response = $this->post($mime);

        // El permiso pudo vencer o revocarse antes de tiempo: uno nuevo y otra vez.
        if ($response->status() === 401) {
            unset(self::$tokens[$this->tokenKey()]);
            $response = $this->post($mime);
        }

        if (! $response->successful()) {
            throw new TransportException($this->describe('Microsoft Graph', $response), $response->status());
        }
    }

    public function __toString(): string
    {
        return 'microsoft-graph://'.$this->mailbox;
    }

    private function post(string $mime): Response
    {
        return Http::withToken($this->token())
            ->withBody($mime, 'text/plain')
            // Corto: la cola corta el trabajo a los 85 s (ver QueuedMailNotification).
            ->timeout(25)
            ->post(sprintf(self::GRAPH_URL, rawurlencode($this->mailbox)));
    }

    private function token(): string
    {
        $cached = self::$tokens[$this->tokenKey()] ?? null;

        if ($cached !== null && $cached['expires'] > time()) {
            return $cached['token'];
        }

        $response = Http::asForm()->timeout(15)->post(sprintf(self::TOKEN_URL, rawurlencode($this->tenantId)), [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new TransportException($this->describe('Microsoft (inicio de sesión de la app)', $response), $response->status());
        }

        self::$tokens[$this->tokenKey()] = [
            'token' => $token,
            'expires' => time() + max(60, (int) $response->json('expires_in', 3600) - 300),
        ];

        return $token;
    }

    private function tokenKey(): string
    {
        return $this->tenantId.'|'.$this->clientId;
    }

    /**
     * El error de Microsoft en una línea, con su código (AADSTS…, ErrorAccessDenied…)
     * para que pqrs:probar-correo pueda explicarlo.
     */
    private function describe(string $who, Response $response): string
    {
        $error = $response->json('error');

        $detail = is_array($error)
            // Graph: {"error": {"code": "...", "message": "..."}}
            ? trim(($error['code'] ?? '').': '.($error['message'] ?? ''), ': ')
            // Inicio de sesión: {"error": "...", "error_description": "AADSTS…: … Trace ID: …"}
            : trim((string) $error.': '.Str::before(Str::before((string) $response->json('error_description'), "\r\n"), ' Trace ID:'), ': ');

        return "{$who} respondió {$response->status()}".($detail !== '' ? " ({$detail})" : '').'.';
    }
}
