<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\PqrsArrivedAtSede;
use App\Models\User;
use App\Notifications\NewPqrsForSedeNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Avisa por correo a los agentes activos de la sede a la que llegó la PQRS
 * (un agente con varias sedes recibe el aviso de todas ellas). Si la PQRS no
 * tiene sede, o la sede no tiene agentes, avisa a los administradores de la
 * empresa para que nadie la pase por alto.
 */
class NotifySedeAgents
{
    public function handle(PqrsArrivedAtSede $event): void
    {
        $pqrs = $event->pqrs;

        /** @var Collection<int, User> $recipients */
        $recipients = $pqrs->sede_id === null
            ? new Collection
            : User::query()
                ->where('is_active', true)
                ->where('role', UserRole::Agente)
                ->whereHas('sedes', fn ($q) => $q->whereKey($pqrs->sede_id))
                ->get();

        $recipients = $recipients->reject(fn (User $u) => $u->id === $event->movedBy?->id);

        if ($recipients->isEmpty()) {
            $recipients = User::query()
                ->where('is_active', true)
                ->where('role', UserRole::Admin)
                ->get()
                ->reject(fn (User $u) => $u->id === $event->movedBy?->id);
        }

        if ($recipients->isEmpty()) {
            return;
        }

        // Un correo que falla no debe impedir radicar ni trasladar.
        try {
            Notification::send($recipients, new NewPqrsForSedeNotification($pqrs, $event->tenant, $event->movedBy));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
