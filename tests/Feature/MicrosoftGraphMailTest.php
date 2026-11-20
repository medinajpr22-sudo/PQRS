<?php

use App\Mail\MicrosoftGraphTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * Envío por Microsoft 365 con OAuth (MAIL_MAILER=microsoft-graph). Microsoft
 * se simula con Http::fake: las pruebas no salen a internet.
 */

beforeEach(function () {
    MicrosoftGraphTransport::forgetTokens();

    config([
        'mail.default' => 'microsoft-graph',
        'mail.from.address' => 'pqrs@empresa.test',
        'mail.mailers.microsoft-graph' => [
            'transport' => 'microsoft-graph',
            'tenant_id' => 'org-123',
            'client_id' => 'app-456',
            'client_secret' => 'secreto-de-prueba',
        ],
    ]);
});

afterEach(fn () => MicrosoftGraphTransport::forgetTokens());

function graphToken(string $token): array
{
    return ['access_token' => $token, 'expires_in' => 3600, 'token_type' => 'Bearer'];
}

function isGraphSend(Request $request): bool
{
    return str_contains($request->url(), 'graph.microsoft.com');
}

test('emails go out through microsoft 365 with oauth and the whole message', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(graphToken('permiso-1')),
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);

    Mail::raw('Primer mensaje', fn ($m) => $m->to('ana@gmail.com')->subject('Prueba uno'));
    Mail::raw('Segundo mensaje', fn ($m) => $m->to('luis@gmail.com')->subject('Prueba dos'));

    // Un solo permiso para los dos correos: se guarda mientras dura.
    Http::assertSentCount(3);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://login.microsoftonline.com/org-123/oauth2/v2.0/token'
        && $r['client_id'] === 'app-456'
        && $r['client_secret'] === 'secreto-de-prueba'
        && $r['grant_type'] === 'client_credentials'
        && $r['scope'] === 'https://graph.microsoft.com/.default');

    Http::assertSent(function (Request $r) {
        if (! isGraphSend($r)) {
            return false;
        }

        $mime = (string) base64_decode($r->body(), true);

        return $r->url() === 'https://graph.microsoft.com/v1.0/users/pqrs%40empresa.test/sendMail'
            && $r->hasHeader('Authorization', 'Bearer permiso-1')
            && $r->hasHeader('Content-Type', 'text/plain')
            && str_contains($mime, 'Subject: Prueba uno')
            && str_contains($mime, 'To: ana@gmail.com')
            && str_contains($mime, 'From: ');
    });
});

test('an expired or revoked permission is renewed once and the email still goes out', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::sequence()
            ->push(graphToken('permiso-viejo'))
            ->push(graphToken('permiso-nuevo')),
        'graph.microsoft.com/*' => Http::sequence()
            ->push(['error' => ['code' => 'InvalidAuthenticationToken', 'message' => 'Lifetime validation failed']], 401)
            ->push(null, 202),
    ]);

    Mail::raw('Hola', fn ($m) => $m->to('ana@gmail.com')->subject('Prueba'));

    $sends = Http::recorded(fn (Request $r) => isGraphSend($r))->values();

    expect($sends)->toHaveCount(2)
        ->and($sends[1][0]->header('Authorization'))->toBe(['Bearer permiso-nuevo']);
});

test('microsoft errors stop the email with a clear reason', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(graphToken('permiso')),
        'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied', 'message' => 'Access is denied. Check credentials and try again.']], 403),
    ]);

    expect(fn () => Mail::raw('Hola', fn ($m) => $m->to('ana@gmail.com')->subject('Prueba')))
        ->toThrow(TransportException::class, 'Microsoft Graph respondió 403 (ErrorAccessDenied: Access is denied.');

    // Graph acepta hasta 4 MB: un correo más pesado ni se intenta.
    expect(fn () => Mail::raw('Hola', fn ($m) => $m->to('ana@gmail.com')->subject('Pesado')
        ->attachData(random_bytes(3_200_000), 'grande.bin')))
        ->toThrow(TransportException::class, 'Microsoft Graph acepta hasta 4 MB');

    config(['mail.mailers.microsoft-graph.client_secret' => '']);
    app('mail.manager')->purge('microsoft-graph');

    expect(fn () => Mail::raw('Hola', fn ($m) => $m->to('ana@gmail.com')->subject('Prueba')))
        ->toThrow(TransportException::class, 'Falta configurar en el .env: MICROSOFT_GRAPH_CLIENT_SECRET.');
});

test('the mail test command explains microsoft errors without showing the secret', function () {
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([
            'error' => 'invalid_client',
            'error_description' => "AADSTS7000215: Invalid client secret provided.\r\nTrace ID: abc",
        ], 401),
    ]);

    $this->artisan('pqrs:probar-correo', ['correo' => 'ana@gmail.com'])
        ->expectsOutputToContain('configurado')
        ->expectsOutputToContain('AADSTS7000215: Invalid client secret provided.')
        ->expectsOutputToContain('«Valor» del secreto')
        ->doesntExpectOutputToContain('secreto-de-prueba')
        ->assertFailed();

    Http::assertNotSent(fn (Request $r) => isGraphSend($r));
});
