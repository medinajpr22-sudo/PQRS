<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Página de inicio del portal PQRS de la empresa.
     */
    public function __invoke(): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $schema = $tenant->resolvedFormSchema();

        return Inertia::render('tenant/portal/home', [
            'types' => collect($schema['types'])->where('enabled', true)->values(),
            'privacyPolicyUrl' => $schema['privacy_policy_url'],
        ]);
    }
}
