<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Models\Tenant;
use App\Services\Captcha;
use App\Services\PqrsFormRules;
use App\Services\PqrsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PqrsSubmissionController extends Controller
{
    public function create(Request $request, Captcha $captcha): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $schema = $tenant->resolvedFormSchema();

        return Inertia::render('tenant/portal/create', [
            'schema' => [
                ...$schema,
                'types' => collect($schema['types'])->where('enabled', true)->values(),
            ],
            'documentTypes' => collect(PqrsFormRules::DOCUMENT_TYPES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'attachmentRules' => [
                'max_kb' => config('pqrs.attachments.max_kb'),
                'mimes' => config('pqrs.attachments.mimes'),
            ],
            'initialType' => $request->query('tipo'),
            'riskLevels' => ! empty($schema['risk_levels']) ? RiskLevel::options() : [],
            'initialRisk' => RiskLevel::tryFrom((string) $request->query('riesgo'))?->value,
            // Si la empresa tiene sedes, el ciudadano elige a cuál dirige su solicitud.
            'sedes' => Sede::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Sede $s) => ['value' => $s->id, 'label' => $s->name]),
            'captcha' => $captcha->enabled(),
        ]);
    }

    /**
     * Reto para la casilla «No soy un robot» del formulario.
     */
    public function captcha(Captcha $captcha): JsonResponse
    {
        abort_unless($captcha->enabled(), 404);

        return response()->json($captcha->challenge())
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, PqrsService $service, Captcha $captcha): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $schema = $tenant->resolvedFormSchema();

        $rules = PqrsFormRules::rules($schema, array_values(array_map('intval', Sede::active()->pluck('id')->all())));

        if ($captcha->enabled()) {
            $rules['altcha'] = ['required', function (string $attribute, mixed $value, Closure $fail) use ($captcha) {
                if (! $captcha->verify($value)) {
                    $fail('La verificación venció o no es válida. Marque de nuevo «No soy un robot».');
                }
            }];
        }

        $data = $request->validate(
            $rules,
            ['altcha.required' => 'Marque la casilla «No soy un robot».'],
            PqrsFormRules::attributes($schema),
        );

        // Se gasta solo cuando todo el formulario es válido; así el ciudadano
        // puede corregir otros campos sin repetir la verificación.
        if ($captcha->enabled() && ! $captcha->consume($request->input('altcha'))) {
            throw ValidationException::withMessages([
                'altcha' => 'Esta verificación ya se usó. Marque de nuevo «No soy un robot».',
            ]);
        }

        $files = $schema['allow_attachments'] ? array_values($request->file('attachments', [])) : [];

        $pqrs = $service->submit($tenant, $data, $files, $request->ip());

        $type = collect($schema['types'])->firstWhere('key', $pqrs->type);

        // El comprobante se muestra una sola vez (no exponemos datos por URL).
        $request->session()->flash('pqrs_receipt', [
            'radicado' => $pqrs->radicado,
            'type' => $type['label'] ?? $pqrs->type,
            'sede' => $pqrs->sede?->name,
            'risk' => $pqrs->risk_level?->label(),
            'subject' => $pqrs->subject,
            'email' => $pqrs->requester_email,
            'created_at' => $pqrs->created_at,
            'due_at' => $pqrs->due_at,
            'sla_days' => $type['sla_days'] ?? null,
        ]);

        return to_route('tenant.pqrs.receipt');
    }

    public function receipt(Request $request): Response|RedirectResponse
    {
        $receipt = $request->session()->get('pqrs_receipt');

        if (! $receipt) {
            return to_route('tenant.home');
        }

        return Inertia::render('tenant/portal/receipt', ['receipt' => $receipt]);
    }
}
