<?php

namespace App\Notifications;

use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PqrsRespondedNotification extends Notification
{
    use Queueable;

    public function __construct(public Pqrs $pqrs, public PqrsActivity $activity, public Tenant $tenant) {}

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
            ->subject("Respuesta a tu solicitud {$this->pqrs->radicado}")
            ->greeting("Hola {$this->pqrs->requester_name},")
            ->line("Hemos dado respuesta a tu solicitud **{$this->pqrs->radicado}** ({$this->pqrs->subject}):")
            ->line((string) $this->activity->body)
            ->action('Ver solicitud', $this->tenant->portalUrl().'/consultar')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
