<?php

namespace App\Support;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Respuesta para los límites de intentos de los formularios: en vez de la
 * página «429 Too Many Requests», vuelve al formulario con un mensaje en
 * español debajo del campo indicado (lo escrito sigue ahí).
 */
final class TooManyAttempts
{
    /**
     * @return Closure(Request, array<string, mixed>): RedirectResponse
     */
    public static function backTo(string $field, string $what = 'intentos'): Closure
    {
        return function (Request $request, array $headers) use ($field, $what): RedirectResponse {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
            $wait = $seconds < 90 ? "{$seconds} segundos" : ((int) ceil($seconds / 60)).' minutos';

            return back()
                ->withErrors([$field => "Demasiados {$what} seguidos. Espera {$wait} e inténtalo de nuevo."])
                ->withInput($request->only('email', 'radicado'));
        };
    }
}
