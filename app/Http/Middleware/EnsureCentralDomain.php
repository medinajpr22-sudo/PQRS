<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las rutas del panel central (login, dashboard, empresas...) solo responden
 * en los dominios centrales; nunca en el dominio de una empresa.
 */
class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains'), true)) {
            abort(404);
        }

        return $next($request);
    }
}
