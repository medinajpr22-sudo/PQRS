<?php

namespace App\Policies;

use App\Models\Pqrs;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Acceso del personal de la empresa a una PQRS.
 *
 * El administrador de empresa ve y gestiona todas. Un agente solo las de
 * las sedes que tiene asignadas: para él, las demás «no existen» (404), así
 * no se revela qué radicados hay en otras sedes.
 */
class PqrsPolicy
{
    public function view(User $user, Pqrs $pqrs): Response
    {
        return $user->canAccessSede($pqrs->sede_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
