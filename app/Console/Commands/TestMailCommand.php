<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Notifications\MailPreviewNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prueba la configuración de correo del .env (SMTP o Microsoft Graph):
 * envía un correo sencillo en el momento (sin cola) y, si falla, explica el
 * error del servidor.
 *
 *   php artisan pqrs:probar-correo alguien@gmail.com
 *   php artisan pqrs:probar-correo alguien@gmail.com --empresa=nefrouros   (con el diseño de la empresa)
 */
class TestMailCommand extends Command
{
    protected $signature = 'pqrs:probar-correo
        {correo : Dirección a la que llegará la prueba}
        {--empresa= : Id de una empresa: envía un correo de ejemplo con su logo y sus colores}';

    protected $description = 'Envía un correo de prueba con la configuración del .env y explica el error si falla';

    /**
     * Pistas para los errores más comunes de Outlook / Microsoft 365 y
     * Gmail. Se muestra la primera que aparezca en el error.
     *
     * @var array<string, string>
     */
    private const HINTS = [
        // Microsoft Graph (MAIL_MAILER=microsoft-graph). Ver docs/correo-microsoft-365.md.
        'Falta configurar en el .env' => 'Completa esos datos en el .env (ver docs/correo-microsoft-365.md).',
        'AADSTS7000215' => 'El secreto de la app no es válido: en MICROSOFT_GRAPH_CLIENT_SECRET va el «Valor» del secreto, no su «Id. de secreto».',
        'AADSTS7000222' => 'El secreto de la app venció. Crea uno nuevo en Microsoft Entra (Registros de aplicaciones → la app → Certificados y secretos) y cámbialo en el .env.',
        'AADSTS700016' => 'Microsoft no encuentra la app: revisa MICROSOFT_GRAPH_CLIENT_ID (Id. de aplicación) y MICROSOFT_GRAPH_TENANT_ID (Id. de directorio).',
        'AADSTS90002' => 'Microsoft no encuentra la organización: revisa MICROSOFT_GRAPH_TENANT_ID (Id. de directorio o inquilino).',
        'AADSTS900023' => 'MICROSOFT_GRAPH_TENANT_ID no tiene el formato correcto: es el «Id. de directorio (inquilino)» de la app.',
        'AADSTS53003' => 'Una política de acceso condicional de Microsoft Entra bloquea a la app: un administrador debe excluirla de esa política.',
        'ErrorAccessDenied' => 'La app no tiene permiso para enviar desde ese buzón. Revisa la asignación «Application Mail.Send» en Exchange (paso 4 de docs/correo-microsoft-365.md). Los cambios de permisos tardan hasta 2 horas en aplicarse.',
        'ErrorInvalidUser' => 'El buzón de MAIL_FROM_ADDRESS no existe en Microsoft 365 o no tiene licencia de Exchange.',
        'MailboxNotEnabledForRESTAPI' => 'El buzón de MAIL_FROM_ADDRESS no existe en Microsoft 365 o no tiene licencia de Exchange.',
        'ErrorSendAsDenied' => 'Graph no deja enviar a nombre de otra dirección: el remitente debe ser el buzón de MAIL_FROM_ADDRESS.',
        'Microsoft Graph acepta hasta 4 MB' => 'Baja PQRS_MAIL_MAX_ATTACHMENTS_KB para que los adjuntos grandes se descarguen desde la consulta.',
        'ApplicationThrottled' => 'Microsoft está frenando los envíos por exceso. La cola los reintenta sola; si pasa seguido, baja PQRS_MAIL_PER_MINUTE.',
        'respondió 429' => 'Microsoft está frenando los envíos por exceso. La cola los reintenta sola; si pasa seguido, baja PQRS_MAIL_PER_MINUTE.',
        // SMTP (Outlook / Microsoft 365 y Gmail).
        'SmtpClientAuthentication is disabled' => 'Microsoft 365 tiene apagado el envío por SMTP para este buzón. Un administrador de Microsoft 365 debe activar «SMTP autenticado» al buzón (Centro de administración → Usuarios → el usuario → Correo → Administrar aplicaciones de correo).',
        'basic authentication is disabled' => 'Microsoft ya no acepta usuario y contraseña por SMTP para esta cuenta. En una cuenta personal de Outlook.com no se puede volver a activar: hay que enviar con OAuth (Microsoft Graph) o con un servicio de envío de correos.',
        '5.7.30' => 'Microsoft apagó el envío con usuario y contraseña (autenticación básica) para este buzón. En Microsoft 365 el administrador puede reactivarlo por un tiempo; la solución definitiva es enviar con OAuth (Microsoft Graph).',
        'security defaults' => 'Los «valores predeterminados de seguridad» de Microsoft Entra bloquean el envío por SMTP con contraseña en toda la organización (y no dejan crear contraseñas de aplicación). En vez de apagarlos, envía con Microsoft Graph: ver docs/correo-microsoft-365.md.',
        'credentials were incorrect' => 'Usuario o contraseña incorrectos.',
        'SendAsDenied' => 'Outlook no deja enviar a nombre de otra dirección: MAIL_FROM_ADDRESS debe ser el mismo correo de MAIL_USERNAME.',
        '5.2.252' => 'Outlook no deja enviar a nombre de otra dirección: MAIL_FROM_ADDRESS debe ser el mismo correo de MAIL_USERNAME.',
        'OutboundSpam' => 'Microsoft bloqueó la cuenta por sospecha de spam. Entra a outlook.com con esa cuenta (suele pedir verificar con el celular para desbloquearla) y luego baja PQRS_MAIL_PER_DAY.',
        'WASCL' => 'Microsoft bloqueó la cuenta por sospecha de spam. Entra a outlook.com con esa cuenta (suele pedir verificar con el celular para desbloquearla) y luego baja PQRS_MAIL_PER_DAY.',
        '5.4.5' => 'La cuenta llegó a su límite diario de envíos; se libera en unas 24 horas. Pon PQRS_MAIL_PER_DAY por debajo de ese límite.',
        '5.7.9' => 'Gmail pide una contraseña de aplicación, no la contraseña normal de la cuenta.',
        '5.7.8' => 'Gmail no aceptó el usuario o la contraseña de aplicación.',
        '5.7.57' => 'El servidor pide autenticarse: revisa MAIL_USERNAME y MAIL_PASSWORD, y usa el puerto 587.',
        'Authentication unsuccessful' => 'El servidor rechazó el usuario o la contraseña. Si la cuenta tiene verificación en dos pasos, la contraseña normal no sirve.',
        'Connection could not be established' => 'No hubo conexión con el servidor: revisa MAIL_HOST y MAIL_PORT, y que la red o el firewall dejen salir por ese puerto.',
        'timed out' => 'El servidor no respondió a tiempo: revisa MAIL_HOST y MAIL_PORT, y que la red o el firewall dejen salir por ese puerto.',
    ];

    public function handle(): int
    {
        $to = (string) $this->argument('correo');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $this->components->error("«{$to}» no es un correo válido.");

            return self::INVALID;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host");
        $port = (int) config("mail.mailers.{$mailer}.port");
        $username = (string) config("mail.mailers.{$mailer}.username");
        $from = (string) config('mail.from.address');

        $this->components->twoColumnDetail('Servicio (MAIL_MAILER)', $mailer);

        if ($mailer === 'smtp') {
            $this->components->twoColumnDetail('Servidor', "{$host}:{$port}");
            $this->components->twoColumnDetail('Usuario', $username !== '' ? $username : '(vacío)');
        }

        if ($mailer === 'microsoft-graph') {
            $this->components->twoColumnDetail('Organización (MICROSOFT_GRAPH_TENANT_ID)', $this->graphValue('tenant_id'));
            $this->components->twoColumnDetail('App (MICROSOFT_GRAPH_CLIENT_ID)', $this->graphValue('client_id'));
            // El secreto nunca se muestra.
            $this->components->twoColumnDetail('Secreto (MICROSOFT_GRAPH_CLIENT_SECRET)', $this->graphValue('client_secret') === '(vacío)' ? '(vacío)' : 'configurado');
        }

        $this->components->twoColumnDetail('Remitente (MAIL_FROM_ADDRESS)', $from);
        $this->newLine();

        foreach ($this->warnings($mailer, $host, $port, $username, $from) as $warning) {
            $this->components->warn($warning);
        }

        $company = $this->option('empresa');
        $tenant = null;

        if (is_string($company) && $company !== '') {
            $tenant = Tenant::query()->find($company);

            if (! $tenant instanceof Tenant) {
                $this->components->error("No existe la empresa «{$company}». Empresas: ".Tenant::query()->orderBy('id')->pluck('id')->implode(', '));

                return self::INVALID;
            }

            $this->components->twoColumnDetail('Diseño de la empresa', (string) $tenant->name);
            $this->newLine();
        }

        try {
            if ($tenant instanceof Tenant) {
                // Dentro de la empresa, como los correos reales: su logo y sus colores.
                $tenant->run(fn () => Notification::route('mail', $to)->notifyNow(new MailPreviewNotification($tenant)));
            } else {
                Mail::raw(
                    'Este es un correo de prueba de la plataforma de PQRS. Si lo recibiste, el envío de correos funciona.',
                    fn ($message) => $message->to($to)->subject('Prueba de correo · '.config('app.name')),
                );
            }
        } catch (Throwable $e) {
            $this->components->error('No se pudo enviar el correo.');
            $this->line('  Respuesta del servidor: '.Str::limit(trim($e->getMessage()), 500));
            $this->newLine();
            $this->line('  <fg=yellow>Qué hacer:</> '.$this->hintFor($e->getMessage()));

            return self::FAILURE;
        }

        $this->components->info(in_array($mailer, ['log', 'array'], true)
            ? 'Listo, pero el correo no salió: con este MAIL_MAILER solo queda registrado.'
            : "Correo enviado a {$to}. Si no llega en unos minutos, revisa la carpeta de spam.");

        return self::SUCCESS;
    }

    /**
     * Problemas que se ven en la configuración antes de enviar.
     *
     * @return list<string>
     */
    private function warnings(string $mailer, string $host, int $port, string $username, string $from): array
    {
        if (in_array($mailer, ['log', 'array'], true)) {
            return ["Con MAIL_MAILER={$mailer} los correos no salen de verdad: quedan en el registro (storage/logs)."];
        }

        if ($mailer === 'microsoft-graph') {
            return Str::endsWith(Str::lower($from), ['@outlook.com', '@hotmail.com', '@live.com', '@outlook.es', '@hotmail.es', '@gmail.com'])
                ? ['Microsoft Graph solo envía desde buzones de Microsoft 365 de la empresa: MAIL_FROM_ADDRESS no puede ser un correo personal.']
                : [];
        }

        if ($mailer !== 'smtp') {
            return [];
        }

        $warnings = [];
        $microsoft = Str::contains($host, ['office365.com', 'outlook.com'], ignoreCase: true);
        $personalOutlook = Str::contains($host, 'smtp-mail.outlook.com', ignoreCase: true)
            || Str::endsWith(Str::lower($username), ['@outlook.com', '@hotmail.com', '@live.com', '@outlook.es', '@hotmail.es']);

        if ($personalOutlook) {
            $warnings[] = 'Las cuentas personales de Outlook.com/Hotmail ya no permiten enviar con contraseña por SMTP (desde septiembre de 2024): lo más probable es que falle.';
        }

        if ($microsoft && $port !== 587) {
            $warnings[] = 'Outlook y Microsoft 365 usan el puerto 587 (MAIL_PORT=587).';
        }

        if (($microsoft || Str::contains($host, 'gmail.com', ignoreCase: true))
            && $username !== '' && strcasecmp($username, $from) !== 0) {
            $warnings[] = 'MAIL_FROM_ADDRESS no es igual a MAIL_USERNAME: Outlook rechaza el correo y Gmail cambia el remitente.';
        }

        return $warnings;
    }

    private function graphValue(string $key): string
    {
        $value = trim((string) config("mail.mailers.microsoft-graph.{$key}"));

        return $value !== '' ? $value : '(vacío)';
    }

    private function hintFor(string $error): string
    {
        foreach (self::HINTS as $needle => $hint) {
            if (Str::contains($error, $needle, ignoreCase: true)) {
                return $hint;
            }
        }

        return 'Revisa la configuración de correo del .env (MAIL_* o MICROSOFT_GRAPH_*). Después de cambiarla, reinicia «php artisan queue:work».';
    }
}
