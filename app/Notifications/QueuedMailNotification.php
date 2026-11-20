<?php

namespace App\Notifications;

use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;
use Symfony\Component\Mime\Email;

/**
 * Base de los correos de la plataforma. Todos salen por cola desde el mismo
 * buzón (MAIL_FROM_ADDRESS), con el nombre de la empresa como remitente.
 *
 *   - Respetan el ritmo del buzón (limitador «outgoing-mail», ver
 *     AppServiceProvider): Outlook y Gmail bloquean las cuentas que envían
 *     muchos correos seguidos. El correo que se pasa del tope espera su
 *     turno en la cola; no se pierde.
 *   - Se reintentan si el servidor de correo falla (hasta 5 errores, con
 *     esperas crecientes) durante máximo dos días.
 *   - Avisan que son automáticos, para que las respuestas automáticas
 *     («estoy de vacaciones») no lleguen al buzón que envía. Los avisos de
 *     rebote sí llegan: sirven para ver qué correos están mal escritos.
 */
abstract class QueuedMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Errores del servidor de correo antes de darlo por fallido. */
    public int $maxExceptions = 5;

    /**
     * Si la PQRS se eliminó (p. ej. un super administrador borró spam) antes
     * de enviar el correo, el correo se descarta en vez de fallar.
     */
    public bool $deleteWhenMissingModels = true;

    /** @var list<int> Segundos de espera entre reintentos */
    public array $backoff = [60, 300, 900, 3600];

    /**
     * Mientras espera turno por el límite de envío, no cuenta como intento
     * fallido: solo se rinde pasados dos días.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(2);
    }

    /**
     * @return list<object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new RateLimited('outgoing-mail')];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Mensaje con el remitente de la empresa.
     *
     * @param  bool  $replyToCompany  Si el ciudadano responde el correo, la
     *                                respuesta va al correo de contacto de
     *                                la empresa (si lo tiene) y no al buzón
     *                                de la plataforma.
     */
    protected function newMessage(Tenant $tenant, bool $replyToCompany = false): MailMessage
    {
        $message = (new MailMessage)
            ->from((string) config('mail.from.address'), $tenant->name)
            ->withSymfonyMessage(function (Email $email): void {
                $email->getHeaders()
                    ->addTextHeader('Auto-Submitted', 'auto-generated')
                    ->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
            });

        if ($replyToCompany && filled($tenant->email)) {
            $message->replyTo((string) $tenant->email, $tenant->name);
        }

        return $message;
    }
}
