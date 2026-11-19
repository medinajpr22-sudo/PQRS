<?php

namespace App\Events;

use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una PQRS llegó a una sede: la radicó un ciudadano o alguien del equipo la
 * trasladó desde otra sede. Lo escucha NotifySedeAgents para avisar por
 * correo a los agentes de esa sede.
 */
class PqrsArrivedAtSede
{
    use Dispatchable;

    public function __construct(
        public Pqrs $pqrs,
        public Tenant $tenant,
        public ?User $movedBy = null,
    ) {}
}
