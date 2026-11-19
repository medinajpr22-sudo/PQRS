<?php

namespace App\Notifications;

use App\Models\Pqrs;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Se envía por cola: si el servidor de correo falla, se reintenta y el
 * ciudadano no tiene que esperar el envío para ver su radicado.
 * (La cola sabe de qué empresa es cada correo gracias a QueueTenancyBootstrapper.)
 */
class PqrsReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> Segundos de espera entre reintentos */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Pqrs $pqrs, public Tenant $tenant)
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
        $pqrs = $this->pqrs->loadMissing('sede');

        return (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject("Radicado {$pqrs->radicado} – Hemos recibido tu solicitud")
            ->greeting("Hola {$pqrs->requester_name},")
            ->line('Recibimos tu solicitud y ya está en manos de nuestro equipo. Guarda este número: con él puedes consultar su estado en cualquier momento.')
            ->markdown('notifications::email', [
                'highlight' => ['label' => 'Número de radicado', 'value' => $pqrs->radicado],
                'details' => [
                    'Tipo' => $this->tenant->pqrsTypeLabel($pqrs->type).($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''),
                    'Sede' => $pqrs->sede?->name,
                    'Asunto' => $pqrs->subject,
                    'Radicada el' => $pqrs->created_at->translatedFormat('j \\d\\e F \\d\\e Y'),
                    'Respuesta a más tardar' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Consultar estado', $this->tenant->portalUrl().'/consultar?radicado='.urlencode($pqrs->radicado))
            ->line('Te avisaremos por este mismo medio cuando tengamos una respuesta.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
