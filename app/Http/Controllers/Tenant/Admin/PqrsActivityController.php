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
use Illuminate\Validation\ValidationException;
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
        $user = $request->user();

        // Solo el responsable responde al ciudadano. Quien no lo es debe
        // asumirla primero: el formulario se lo pregunta antes de enviar.
        if ($data['type'] === PqrsActivity::TYPE_RESPONSE && $pqrs->assigned_to !== $user->id) {
            $canTake = $pqrs->assigned_to === null || $user->isAdmin();

            if (! $canTake) {
                throw ValidationException::withMessages([
                    'body' => 'Esta solicitud está a cargo de '.($pqrs->assignee->name ?? 'otra persona').'. Solo esa persona o un administrador pueden responderla.',
                ]);
            }

            if (! $request->boolean('take')) {
                throw ValidationException::withMessages([
                    'body' => 'Para responder debes ser el responsable de esta solicitud.',
                ]);
            }

            $service->assign($tenant, $pqrs, $user, $user);
        }

        $service->addActivity(
            $tenant,
            $pqrs,
            $user,
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
