<?php

namespace App\Notifications;

use App\Models\Pqrs;
use App\Models\Tenant;
use App\Support\MailText;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Se envía por cola: si el servidor de correo falla, se reintenta y el
 * ciudadano no tiene que esperar el envío para ver su radicado.
 * (La cola sabe de qué empresa es cada correo gracias a QueueTenancyBootstrapper.)
 */
class PqrsReceivedNotification extends QueuedMailNotification
{
    public function __construct(public Pqrs $pqrs, public Tenant $tenant)
    {
        $this->afterCommit();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pqrs = $this->pqrs->loadMissing('sede');

        return $this->newMessage($this->tenant, replyToCompany: true)
            ->subject("Radicado {$pqrs->radicado} – Hemos recibido tu solicitud")
            ->greeting('Hola '.MailText::inert($pqrs->requester_name).',')
            ->line('Recibimos tu solicitud y ya está en manos de nuestro equipo. Guarda este número: con él puedes consultar su estado en cualquier momento.')
            ->markdown('notifications::email', [
                'highlight' => ['label' => 'Número de radicado', 'value' => $pqrs->radicado],
                'details' => [
                    'Tipo' => $this->tenant->pqrsTypeLabel($pqrs->type).($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''),
                    'Sede' => $pqrs->sede?->name,
                    'Asunto' => MailText::inert($pqrs->subject),
                    'Radicada el' => $pqrs->created_at->translatedFormat('j \\d\\e F \\d\\e Y'),
                    'Respuesta a más tardar' => $pqrs->due_at?->translatedFormat('j \\d\\e F \\d\\e Y'),
                ],
            ])
            ->action('Consultar estado', $this->tenant->portalUrl().'/consultar?radicado='.urlencode($pqrs->radicado))
            ->line('Te avisaremos por este mismo medio cuando tengamos una respuesta.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
