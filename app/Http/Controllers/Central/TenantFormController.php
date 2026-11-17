<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateTenantFormRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TenantFormController extends Controller
{
    public function update(UpdateTenantFormRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->form_schema = $request->schema();
        $tenant->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Formulario PQRS actualizado.']);

        return back();
    }
}
