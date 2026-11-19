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
        $type = $this->typeLabel($pqrs->type);

        $mail = (new MailMessage)
            ->from(config('mail.from.address'), $this->tenant->name)
            ->subject(($pqrs->risk_level === RiskLevel::Vital ? '[Riesgo vital] ' : '')
                ."Nueva PQRS {$pqrs->radicado}".($sede ? " · sede {$sede}" : ''))
            ->greeting('Hola '.($notifiable->name ?? '').',');

        $mail->line(match (true) {
            $this->movedBy !== null => "{$this->movedBy->name} trasladó la solicitud **{$pqrs->radicado}** a la sede {$sede}.",
            $sede !== null => "Llegó una nueva solicitud a la sede {$sede}: **{$pqrs->radicado}**.",
            default => "Llegó una nueva solicitud sin sede: **{$pqrs->radicado}**.",
        });

        if ($pqrs->risk_level === RiskLevel::Vital) {
            $mail->line('**Es un reclamo de riesgo vital: atiéndelo con prioridad.**');
        }

        return $mail
            ->line("Tipo: {$type}".($pqrs->risk_level ? ' · '.$pqrs->risk_level->label() : ''))
            ->line("Asunto: {$pqrs->subject}")
            ->line("Solicitante: {$pqrs->requester_name}")
            ->line('Vence: '.$pqrs->due_at?->translatedFormat('d \d\e F \d\e Y'))
            ->action('Ver solicitud', $this->tenant->portalUrl().'/admin/pqrs/'.$pqrs->radicado)
            ->line('Para responderla, entra al panel y acepta ser el responsable.')
            ->salutation("Atentamente,\n{$this->tenant->name}");
    }

    /**
     * Nombre del tipo de solicitud como lo configuró la empresa.
     */
    private function typeLabel(string $key): string
    {
        $types = $this->tenant->resolvedFormSchema()['types'] ?? [];

        foreach (is_array($types) ? $types : [] as $type) {
            if (is_array($type) && ($type['key'] ?? null) === $key) {
                return (string) ($type['label'] ?? $key);
            }
        }

        return $key;
    }
}
