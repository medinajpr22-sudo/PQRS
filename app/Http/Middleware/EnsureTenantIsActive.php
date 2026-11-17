<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si la empresa está suspendida, su portal y su panel muestran un aviso.
 */
class EnsureTenantIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if ($tenant instanceof Tenant && ! $tenant->isActive()) {
            return Inertia::render('tenant/portal/suspended')
                ->toResponse($request)
                ->setStatusCode(503);
        }

        return $next($request);
    }
}
