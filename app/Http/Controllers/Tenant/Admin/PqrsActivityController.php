<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Tenant;
use App\Services\PqrsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PqrsActivityController extends Controller
{
    /**
     * Registra una nota interna o envía una respuesta al ciudadano.
     */
    public function store(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([PqrsActivity::TYPE_NOTE, PqrsActivity::TYPE_RESPONSE])],
            'body' => ['required', 'string', 'min:5', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.config('pqrs.attachments.max_kb'), 'mimes:'.implode(',', config('pqrs.attachments.mimes'))],
        ], [], ['body' => 'mensaje', 'attachments.*' => 'archivo adjunto']);

        /** @var Tenant $tenant */
        $tenant = tenant();

        $service->addActivity(
            $tenant,
            $pqrs,
            $request->user(),
            $data['type'],
            $data['body'],
            array_values($request->file('attachments', [])),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['type'] === PqrsActivity::TYPE_RESPONSE
                ? 'Respuesta enviada al ciudadano por correo.'
                : 'Nota interna registrada.',
        ]);

        return back();
    }
}
