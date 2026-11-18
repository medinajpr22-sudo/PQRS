<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\PqrsAttachment;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta pública del estado de una PQRS (radicado + correo).
 */
class PqrsTrackingController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('tenant/portal/track');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'radicado' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:150'],
        ], [], ['radicado' => 'número de radicado', 'email' => 'correo electrónico']);

        $pqrs = Pqrs::where('radicado', Str::upper(trim($data['radicado'])))
            ->where('requester_email', Str::lower(trim($data['email'])))
            ->first();

        if (! $pqrs) {
            throw ValidationException::withMessages([
                'radicado' => 'No encontramos una solicitud con ese número de radicado y correo.',
            ]);
        }

        $request->session()->put("tracking.{$pqrs->radicado}", true);

        return to_route('tenant.tracking.show', $pqrs);
    }

    public function show(Request $request, Pqrs $pqrs): Response|RedirectResponse
    {
        if (! $request->session()->get("tracking.{$pqrs->radicado}")) {
            return to_route('tenant.tracking.create');
        }

        /** @var Tenant $tenant */
        $tenant = tenant();
        $type = collect($tenant->resolvedFormSchema()['types'])->firstWhere('key', $pqrs->type);

        $pqrs->load(['activities' => fn ($q) => $q->where('is_public', true), 'activities.attachments']);

        return Inertia::render('tenant/portal/track-result', [
            'pqrs' => [
                'radicado' => $pqrs->radicado,
                'type' => $type['label'] ?? $pqrs->type,
                'sede' => $pqrs->sede?->name,
                'status' => $pqrs->status->value,
                'status_label' => $pqrs->status->label(),
                'subject' => $pqrs->subject,
                'requester_name' => $pqrs->requester_name,
                'created_at' => $pqrs->created_at,
                'due_at' => $pqrs->due_at,
                'responded_at' => $pqrs->responded_at,
                'activities' => $pqrs->activities->map(fn (PqrsActivity $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'body' => $a->body,
                    'created_at' => $a->created_at,
                    'attachments' => $a->attachments->map(fn (PqrsAttachment $f) => [
                        'id' => $f->id,
                        'name' => $f->original_name,
                        'size' => $f->size,
                    ]),
                ]),
            ],
        ]);
    }

    public function attachment(Request $request, Pqrs $pqrs, PqrsAttachment $attachment): StreamedResponse
    {
        abort_unless($request->session()->get("tracking.{$pqrs->radicado}"), 403);

        // Los adjuntos de notas internas no se exponen al ciudadano, aunque adivine el número.
        abort_unless($attachment->isPublic(), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
