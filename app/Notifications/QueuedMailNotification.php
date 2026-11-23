<?php

namespace App\Notifications;

use App\Models\Tenant;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Base de los correos de la plataforma. Todos salen por cola desde el mismo
 * buzón (MAIL_FROM_ADDRESS), con el nombre de la empresa como remitente.
 *
 *   - Respetan el ritmo del buzón (limitador «outgoing-mail», ver
 *     AppServiceProvider): Outlook y Gmail bloquean las cuentas que envían
 *     muchos correos seguidos. El correo que se pasa del tope espera su
 *     turno en la cola; no se pierde.
 *   - Se reintentan si el servidor de correo falla, con esperas crecientes
 *     (de 1 minuto a 8 horas): aguantan unas 48 horas de caída del servidor
 *     de correo (o un secreto de Microsoft vencido un viernes). Si al final
 *     no sale, queda en el registro (storage/logs) para revisarlo.
 *   - Avisan que son automáticos, para que las respuestas automáticas
 *     («estoy de vacaciones») no lleguen al buzón que envía. Los avisos de
 *     rebote sí llegan: sirven para ver qué correos están mal escritos.
 */
abstract class QueuedMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Errores del servidor de correo antes de darlo por fallido (ver $backoff). */
    public int $maxExceptions = 12;

    /**
     * Segundos que puede tardar un envío antes de que la cola lo corte. Menos
     * que retry_after (90 s, config/queue.php): si no, otro proceso de la
     * cola podría tomar el mismo correo y enviarlo dos veces.
     */
    public int $timeout = 85;

    /**
     * Si la PQRS se eliminó (p. ej. un super administrador borró spam) antes
     * de enviar el correo, el correo se descarta en vez de fallar.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Segundos de espera entre reintentos: 1 y 5 minutos, luego 15 y 30
     * minutos, 1, 2, 4 y 8 horas (y 8 horas de ahí en adelante).
     *
     * @var list<int>
     */
    public array $backoff = [60, 300, 900, 1800, 3600, 7200, 14400, 28800];

    /**
     * Mientras espera turno por el límite de envío, no cuenta como intento
     * fallido: solo se rinde pasada una semana desde que se creó.
     *
     * El plazo corre aunque la cola esté detenida: con dos días, un correo
     * del viernes se perdía si la cola volvía a arrancar el lunes (o después
     * de un puente festivo). Una respuesta tardía sigue siendo útil para el
     * ciudadano; una que nunca llega, no.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addWeek();
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
     * El correo no salió después de todos los reintentos: queda en el
     * registro con la empresa y el error, para revisarlo (y reenviarlo con
     * «php artisan queue:retry» cuando se solucione).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo enviar un correo de la plataforma', [
            'correo' => class_basename(static::class),
            'empresa' => tenant('id'),
            'error' => Str::limit($exception->getMessage(), 300),
        ]);
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
