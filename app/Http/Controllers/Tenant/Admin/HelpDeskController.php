<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mesa de ayuda: para administradores y agentes que necesitan soporte con la
 * aplicación o quieren proponer una mejora. Los lleva a la mesa de ayuda de
 * TIC (config/pqrs.php → help_desk_url), con los datos que conviene poner
 * en el ticket listos para copiar.
 */
class HelpDeskController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        return Inertia::render('tenant/admin/help', [
            'helpDeskUrl' => (string) config('pqrs.help_desk_url'),
            'company' => (string) $tenant->name,
            'portalUrl' => $tenant->portalUrl(),
        ]);
    }
}
