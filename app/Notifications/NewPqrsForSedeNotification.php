<?php

namespace App\Notifications;

use App\Enums\RiskLevel;
use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Aviso a los agentes de una sede cuando les llega una PQRS nueva o
 * trasladada. Se envía por cola, con reintentos.
 */
class NewPqrsForSedeNotification extends QueuedMailNotification
{
    public function __construct(public Pqrs $pqrs, public Tenant $tenant, public ?User $movedBy = null)
    {
        $this->afterCommit();
    }

    /**
     * El correo pudo esperar en la cola: si mientras tanto desactivaron a la
     * persona o le quitaron la sede, ya no le llegan los datos del paciente.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User
            || ($notifiable->is_active && $notifiable->canAccessSede($this->pqrs->sede_id));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pqrs = $this->pqrs->loadMissing('sede');
        $sede = $pqrs->sede?->name;
        $vital = $pqrs->risk_level === RiskLevel::Vital;

        return $this->newMessage($this->tenant)
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
                    'Asunto' => MailText::inert($pqrs->subject),
                    'Servicio' => $pqrs->service,
                    'Solicitante' => MailText::inert($pqrs->requester_name),
                    'Paciente' => $pqrs->requester_is_affected === false ? $pqrs->affected_name : null,
                    'Vence' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$pqrs->radicado)
            ->line('Para responderla, entra al panel y acepta ser el responsable.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
