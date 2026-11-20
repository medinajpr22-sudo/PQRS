<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo de ejemplo con el diseño de una empresa (logo, colores y botón),
 * para revisar cómo les llegan los correos a los ciudadanos. Lo envía
 * «php artisan pqrs:probar-correo correo --empresa=id».
 */
class MailPreviewNotification extends QueuedMailNotification
{
    public function __construct(public Tenant $tenant) {}

    public function toMail(object $notifiable): MailMessage
    {
        $year = now()->year;

        return $this->newMessage($this->tenant, replyToCompany: true)
            ->subject("Prueba de correo · {$this->tenant->name}")
            ->greeting('¡Hola!')
            ->line("Este es un correo de prueba. Así verán los ciudadanos los mensajes de {$this->tenant->name}: con su logo, sus colores y el botón para consultar.")
            ->markdown('notifications::email', [
                'highlight' => [
                    'label' => 'Número de radicado (ejemplo)',
                    'value' => "{$this->tenant->radicado_prefix}-{$year}-00000",
                ],
            ])
            ->action('Ir al portal', $this->tenant->portalUrl() ?? (string) config('app.url'))
            ->line('Si lo recibiste, el envío de correos funciona.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }
}
