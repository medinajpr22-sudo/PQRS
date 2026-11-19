<?php

namespace App\Notifications;

use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\PqrsAttachment;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Le lleva al ciudadano la respuesta completa, con sus archivos adjuntos.
 * Se envía por cola, con reintentos (ver PqrsReceivedNotification).
 */
class PqrsRespondedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Por encima de este total, los adjuntos se descargan desde la consulta. */
    private const MAX_ATTACHMENTS_BYTES = 10 * 1024 * 1024;

    public int $tries = 5;

    /** @var list<int> Segundos de espera entre reintentos */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Pqrs $pqrs, public PqrsActivity $activity, public Tenant $tenant)
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $note = null;
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject("Respuesta a tu solicitud {$this->pqrs->radicado}")
            ->greeting("Hola {$this->pqrs->requester_name},")
            ->line('Ya tenemos respuesta para tu solicitud. Esto es lo que nuestro equipo te responde:');

        $files = $this->activity->attachments()->get();

        if ($files->isNotEmpty()) {
            $disk = Storage::disk('local');
            $fits = $files->sum('size') <= self::MAX_ATTACHMENTS_BYTES
                && $files->every(fn (PqrsAttachment $f) => $disk->exists($f->path));

            if ($fits) {
                foreach ($files as $file) {
                    $mail->attach($disk->path($file->path), array_filter(['as' => $file->original_name, 'mime' => $file->mime_type]));
                }

                $note = 'Adjuntamos '.($files->count() === 1 ? 'el archivo' : 'los archivos').' de la respuesta.';
            } else {
                $note = 'La respuesta incluye archivos adjuntos: puedes descargarlos al consultar tu radicado.';
            }
        }

        return $mail
            ->markdown('notifications::email', [
                'details' => [
                    'Radicado' => $this->pqrs->radicado,
                    'Asunto' => $this->pqrs->subject,
                ],
                'quote' => (string) $this->activity->body,
                'quoteNote' => $note,
            ])
            ->action('Consultar mi solicitud', $this->tenant->portalUrl().'/consultar?radicado='.urlencode($this->pqrs->radicado))
            ->line('Si tienes alguna duda sobre esta respuesta, puedes radicar una nueva solicitud en nuestro portal.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
