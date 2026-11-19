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
 * Aviso al agente o administrador cuando le asignan una PQRS.
 */
class PqrsAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> Segundos de espera entre reintentos */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Pqrs $pqrs, public Tenant $tenant, public User $assignedBy)
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
            ->subject("Te asignaron la solicitud {$pqrs->radicado}")
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line("{$this->assignedBy->name} te asignó esta solicitud: ahora estás a cargo de responderla.")
            ->markdown('notifications::email', [
                'alert' => $pqrs->risk_level === RiskLevel::Vital ? 'Reclamo de riesgo vital: atiéndelo con prioridad.' : null,
                'highlight' => ['label' => 'Solicitud asignada', 'value' => $pqrs->radicado],
                'details' => [
                    'Tipo' => $this->tenant->pqrsTypeLabel($pqrs->type).($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''),
                    'Sede' => $pqrs->sede?->name,
                    'Asunto' => $pqrs->subject,
                    'Solicitante' => $pqrs->requester_name,
                    'Vence' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$pqrs->radicado)
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
