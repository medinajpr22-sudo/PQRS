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
use Illuminate\Support\HtmlString;

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
        $mail = (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject("Respuesta a tu solicitud {$this->pqrs->radicado}")
            ->greeting("Hola {$this->pqrs->requester_name},")
            ->line("Hemos dado respuesta a tu solicitud **{$this->pqrs->radicado}** ({$this->pqrs->subject}):")
            // Respeta los saltos de línea que escribió el funcionario.
            ->line(new HtmlString(nl2br(e((string) $this->activity->body), false)));

        $files = $this->activity->attachments()->get();

        if ($files->isNotEmpty()) {
            $disk = Storage::disk('local');
            $fits = $files->sum('size') <= self::MAX_ATTACHMENTS_BYTES
                && $files->every(fn (PqrsAttachment $f) => $disk->exists($f->path));

            if ($fits) {
                foreach ($files as $file) {
                    $mail->attach($disk->path($file->path), array_filter(['as' => $file->original_name, 'mime' => $file->mime_type]));
                }

                $mail->line('Adjuntamos '.($files->count() === 1 ? 'el archivo' : 'los archivos').' de la respuesta.');
            } else {
                $mail->line('La respuesta incluye archivos adjuntos: puedes descargarlos al consultar tu radicado.');
            }
        }

        return $mail
            ->action('Consultar mi solicitud', $this->tenant->portalUrl().'/consultar?radicado='.urlencode($this->pqrs->radicado))
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
