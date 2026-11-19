<?php

namespace App\Notifications;

use App\Enums\RiskLevel;
use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso a los agentes de una sede cuando les llega una PQRS nueva o
 * trasladada. Se envía por cola, con reintentos.
 */
class NewPqrsForSedeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> Segundos de espera entre reintentos */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Pqrs $pqrs, public Tenant $tenant, public ?User $movedBy = null)
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
        $sede = $pqrs->sede?->name;
        $vital = $pqrs->risk_level === RiskLevel::Vital;

        return (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject(($vital ? '[Riesgo vital] ' : '')
                ."Nueva PQRS {$pqrs->radicado}".($sede ? " · sede {$sede}" : ''))
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line(match (true) {
                $this->movedBy !== null => "{$this->movedBy->name} trasladó esta solicitud a la sede {$sede}.",
                $sede !== null => "Llegó una nueva solicitud a la sede {$sede}.",
                default => 'Llegó una nueva solicitud.',
            })
            ->markdown('notifications::email', [
                'alert' => $vital ? 'Reclamo de riesgo vital: atiéndelo con prioridad.' : null,
                'highlight' => [
                    'label' => $this->movedBy !== null ? 'Solicitud trasladada' : 'Nueva solicitud',
                    'value' => $pqrs->radicado,
                ],
                'details' => [
                    'Tipo' => $this->tenant->pqrsTypeLabel($pqrs->type).($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''),
                    'Sede' => $sede,
                    'Asunto' => $pqrs->subject,
                    'Solicitante' => $pqrs->requester_name,
                    'Vence' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$pqrs->radicado)
            ->line('Para responderla, entra al panel y acepta ser el responsable.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
