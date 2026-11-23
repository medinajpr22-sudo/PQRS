<?php

namespace App\Notifications;

use App\Enums\RiskLevel;
use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Aviso al agente o administrador cuando le asignan una PQRS.
 */
class PqrsAssignedNotification extends QueuedMailNotification
{
    public function __construct(public Pqrs $pqrs, public Tenant $tenant, public User $assignedBy)
    {
        $this->afterCommit();
    }

    /**
     * El correo pudo esperar en la cola: si mientras tanto la reasignaron o
     * desactivaron a la persona, el aviso ya no aplica.
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User
            || ($notifiable->is_active && $this->pqrs->assigned_to === $notifiable->id);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pqrs = $this->pqrs->loadMissing('sede');

        return $this->newMessage($this->tenant)
            ->subject("Te asignaron la solicitud {$pqrs->radicado}")
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line("{$this->assignedBy->name} te asignó esta solicitud: ahora estás a cargo de responderla.")
            ->markdown('notifications::email', [
                'alert' => $pqrs->risk_level === RiskLevel::Vital ? 'Reclamo de riesgo vital: atiéndelo con prioridad.' : null,
                'highlight' => ['label' => 'Solicitud asignada', 'value' => $pqrs->radicado],
                'details' => [
                    'Tipo' => $this->tenant->pqrsTypeLabel($pqrs->type).($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''),
                    'Sede' => $pqrs->sede?->name,
                    'Asunto' => MailText::inert($pqrs->subject),
                    'Solicitante' => MailText::inert($pqrs->requester_name),
                    'Vence' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$pqrs->radicado)
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
