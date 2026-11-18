<?php

namespace App\Notifications;

use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al funcionario cuando le asignan una PQRS.
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
        return (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject("Te asignaron la solicitud {$this->pqrs->radicado}")
            ->greeting('Hola '.($notifiable->name ?? '').',')
            ->line("{$this->assignedBy->name} te asignó la solicitud **{$this->pqrs->radicado}**.")
            ->line("Asunto: {$this->pqrs->subject}")
            ->line('Vence: '.$this->pqrs->due_at?->translatedFormat('d \d\e F \d\e Y'))
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$this->pqrs->radicado)
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
