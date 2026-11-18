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
        return (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject("Radicado {$this->pqrs->radicado} – Hemos recibido tu solicitud")
            ->greeting("Hola {$this->pqrs->requester_name},")
            ->line("Tu solicitud fue radicada con el número **{$this->pqrs->radicado}**.")
            ->line("Asunto: {$this->pqrs->subject}")
            ->line('Fecha máxima de respuesta: '.$this->pqrs->due_at?->translatedFormat('d \d\e F \d\e Y'))
            ->action('Consultar estado', $this->tenant->portalUrl().'/consultar')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
