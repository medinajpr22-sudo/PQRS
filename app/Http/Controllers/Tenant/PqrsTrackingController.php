<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\PqrsAttachment;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
    /** Intentos fallidos por correo o por radicado, cada hora. */
    private const MAX_FAILED_ATTEMPTS = 8;

    public function create(Request $request): Response
    {
        // La barra «Hacer seguimiento» del inicio trae el radicado ya escrito;
        // el correo se pide aquí para que nadie consulte solicitudes ajenas.
        return Inertia::render('tenant/portal/track', [
            'radicado' => is_string($radicado = $request->query('radicado')) ? (Str::upper(trim($radicado)) ?: null) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'radicado' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:150'],
        ], [], ['radicado' => 'número de radicado', 'email' => 'correo electrónico']);

        $radicado = Str::upper(trim($data['radicado']));
        $email = Str::lower(trim($data['email']));

        // Los radicados son consecutivos: además del tope por conexión, se
        // limitan los intentos fallidos por correo y por radicado, para que
        // nadie pruebe números con el correo de otra persona (o al revés).
        $keys = [
            'tracking-email|'.tenant('id').'|'.$email,
            'tracking-radicado|'.tenant('id').'|'.$radicado,
        ];

        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::MAX_FAILED_ATTEMPTS)) {
                throw ValidationException::withMessages([
                    'radicado' => 'Demasiados intentos. Vuelve a intentarlo en '.ceil(RateLimiter::availableIn($key) / 60).' minutos.',
                ]);
            }
        }

        $pqrs = Pqrs::where('radicado', $radicado)
            ->where('requester_email', $email)
            ->first();

        if (! $pqrs) {
            foreach ($keys as $key) {
                RateLimiter::hit($key, 3600);
            }

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
                'risk' => $pqrs->risk_level?->label(),
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

    public function attachment(Request $request, Pqrs $pqrs, PqrsAttachment $attachment): StreamedResponse|RedirectResponse
    {
        // Igual que si el radicado no existiera (ver ->missing() en las rutas).
        if (! $request->session()->get("tracking.{$pqrs->radicado}")) {
            return to_route('tenant.tracking.create');
        }

        // Los adjuntos de notas internas no se exponen al ciudadano, aunque adivine el número.
        abort_unless($attachment->isPublic(), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }
}
