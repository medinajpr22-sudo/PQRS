<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el administrador de empresa desactiva a un usuario, este pierde el acceso
 * de inmediato, aunque tuviera una sesión abierta.
 */
class EnsureStaffIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_active === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('tenant.admin.login')->withErrors([
                'email' => 'Tu usuario fue desactivado. Comunícate con el administrador de tu empresa.',
            ]);
        }

        return $next($request);
    }
}
