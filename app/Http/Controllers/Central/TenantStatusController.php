<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TenantStatusController extends Controller
{
    /**
     * Activa o suspende el portal de una empresa.
     *
     * Se pide el estado final (no «cambiar»): un doble clic, o dos personas a
     * la vez, no la suspenden y la vuelven a activar sin querer.
     */
    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED])],
        ]);

        $tenant->status = $data['status'];
        $tenant->save();

        Inertia::flash('toast', [
            'type' => $tenant->isActive() ? 'success' : 'warning',
            'message' => $tenant->isActive() ? 'Empresa activada.' : 'Empresa suspendida: su portal ya no recibe solicitudes.',
        ]);

        return back();
    }
}
