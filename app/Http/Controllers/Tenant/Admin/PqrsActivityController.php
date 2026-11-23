<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Tenant;
use App\Services\PqrsService;
use App\Support\UploadLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PqrsActivityController extends Controller
{
    /** Archivos por respuesta o nota. */
    public const MAX_FILES = 5;

    /**
     * Registra una nota interna o envía una respuesta al ciudadano.
     */
    public function store(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([PqrsActivity::TYPE_NOTE, PqrsActivity::TYPE_RESPONSE])],
            'body' => ['required', 'string', 'min:5', 'max:10000'],
            ...UploadLimits::rules('attachments', self::MAX_FILES),
        ], UploadLimits::messages('attachments', self::MAX_FILES), ['body' => 'mensaje', 'attachments.*' => 'archivo adjunto']);

        /** @var Tenant $tenant */
        $tenant = tenant();
        $user = $request->user();

        // Solo el responsable (o un administrador) responde al ciudadano.
        // Quien no lo es debe asumirla («take»): el formulario se lo pregunta
        // antes de enviar, y el servicio lo hace junto con la respuesta.
        $service->addActivity(
            $tenant,
            $pqrs,
            $user,
            $data['type'],
            $data['body'],
            array_values($request->file('attachments', [])),
            take: $request->boolean('take'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $data['type'] === PqrsActivity::TYPE_RESPONSE
                ? 'Respuesta registrada. El correo al ciudadano sale en unos minutos.'
                : 'Nota interna registrada.',
        ]);

        return back();
    }
}
