<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cierra las sesiones abiertas de un usuario (sesiones en la base de datos).
 *
 * Se usa cuando cambia una contraseña: si alguien más la conocía y tenía
 * una sesión abierta, la pierde.
 */
final class UserSessions
{
    /**
     * @param  string|null  $except  Sesión que se conserva (la de quien hace el cambio).
     */
    public static function end(User $user, ?string $except = null): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $user->getKey())
            ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))
            ->delete();
    }
}
