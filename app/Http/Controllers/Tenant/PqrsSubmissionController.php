<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\PqrsFormRules;
use App\Services\PqrsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PqrsSubmissionController extends Controller
{
    public function create(Request $request): Response
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
        ]);
    }

    public function store(Request $request, PqrsService $service): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $schema = $tenant->resolvedFormSchema();

        $data = $request->validate(
            PqrsFormRules::rules($schema),
            [],
            PqrsFormRules::attributes($schema),
        );

        $files = $schema['allow_attachments'] ? array_values($request->file('attachments', [])) : [];

        $pqrs = $service->submit($tenant, $data, $files, $request->ip());

        $type = collect($schema['types'])->firstWhere('key', $pqrs->type);

        // El comprobante se muestra una sola vez (no exponemos datos por URL).
        $request->session()->flash('pqrs_receipt', [
            'radicado' => $pqrs->radicado,
            'type' => $type['label'] ?? $pqrs->type,
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
