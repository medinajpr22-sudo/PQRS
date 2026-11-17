<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantStatusController extends Controller
{
    /**
     * Activa o suspende el portal de una empresa.
     */
    public function update(Tenant $tenant): RedirectResponse
    {
        $tenant->status = $tenant->isActive() ? Tenant::STATUS_SUSPENDED : Tenant::STATUS_ACTIVE;
        $tenant->save();

        Inertia::flash('toast', [
            'type' => $tenant->isActive() ? 'success' : 'warning',
            'message' => $tenant->isActive() ? 'Empresa activada.' : 'Empresa suspendida: su portal ya no recibe solicitudes.',
        ]);

        return back();
    }
}
