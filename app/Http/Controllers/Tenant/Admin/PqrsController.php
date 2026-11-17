<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\PqrsAttachment;
use App\Models\Tenant;
use App\Services\PqrsFormRules;
use App\Services\PqrsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PqrsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $types = collect($tenant->resolvedFormSchema()['types'])->keyBy('key');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => $request->query('status'),
            'type' => $request->query('type'),
            'overdue' => $request->boolean('overdue'),
        ];

        $pqrs = Pqrs::query()
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('radicado', 'like', "%{$filters['search']}%")
                ->orWhere('requester_name', 'like', "%{$filters['search']}%")
                ->orWhere('requester_email', 'like', "%{$filters['search']}%")
                ->orWhere('requester_document', 'like', "%{$filters['search']}%")
                ->orWhere('subject', 'like', "%{$filters['search']}%")))
            ->when(PqrsStatus::tryFrom((string) $filters['status']), fn ($q, $status) => $q->where('status', $status))
            ->when($types->has((string) $filters['type']), fn ($q) => $q->where('type', $filters['type']))
            ->when($filters['overdue'], fn ($q) => $q->overdue())
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Pqrs $p) => self::listItem($p, $types));

        return Inertia::render('tenant/admin/pqrs/index', [
            'pqrs' => $pqrs,
            'filters' => $filters,
            'statuses' => PqrsStatus::options(),
            'types' => $types->map(fn ($t) => ['value' => $t['key'], 'label' => $t['label']])->values(),
        ]);
    }

    public function show(Pqrs $pqrs): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $types = collect($tenant->resolvedFormSchema()['types'])->keyBy('key');

        $pqrs->load(['activities.user', 'activities.attachments', 'attachments']);

        // Adjuntos enviados por el ciudadano al radicar.
        $createdActivityId = $pqrs->activities->firstWhere('type', PqrsActivity::TYPE_CREATED)?->id;
        $requesterAttachments = $pqrs->attachments
            ->filter(fn (PqrsAttachment $a) => $a->activity_id === null || $a->activity_id === $createdActivityId);

        return Inertia::render('tenant/admin/pqrs/show', [
            'pqrs' => [
                ...self::listItem($pqrs, $types),
                'channel' => $pqrs->channel,
                'requester_document_type' => $pqrs->requester_document_type,
                'requester_document_type_label' => PqrsFormRules::DOCUMENT_TYPES[$pqrs->requester_document_type] ?? null,
                'requester_document' => $pqrs->requester_document,
                'requester_phone' => $pqrs->requester_phone,
                'description' => $pqrs->description,
                'extra_fields' => $pqrs->extra_fields ?? [],
                'responded_at' => $pqrs->responded_at,
                'closed_at' => $pqrs->closed_at,
                'sla_days' => $types[$pqrs->type]['sla_days'] ?? null,
                'attachments' => $requesterAttachments->map(fn (PqrsAttachment $a) => self::attachment($a))->values(),
                'activities' => $pqrs->activities->map(fn (PqrsActivity $a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'body' => $a->body,
                    'is_public' => $a->is_public,
                    'user' => $a->user?->only(['id', 'name']),
                    'created_at' => $a->created_at,
                    'attachments' => $a->type === PqrsActivity::TYPE_CREATED
                        ? []
                        : $a->attachments->map(fn (PqrsAttachment $f) => self::attachment($f)),
                ]),
            ],
            'statuses' => PqrsStatus::options(),
        ]);
    }

    public function updateStatus(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(PqrsStatus::class)],
        ]);

        $service->changeStatus($pqrs, PqrsStatus::from($data['status']), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado actualizado.']);

        return back();
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $types
     * @return array<string, mixed>
     */
    public static function listItem(Pqrs $pqrs, Collection $types): array
    {
        return [
            'id' => $pqrs->id,
            'radicado' => $pqrs->radicado,
            'type' => $pqrs->type,
            'type_label' => $types[$pqrs->type]['label'] ?? $pqrs->type,
            'status' => $pqrs->status->value,
            'status_label' => $pqrs->status->label(),
            'subject' => $pqrs->subject,
            'requester_name' => $pqrs->requester_name,
            'requester_email' => $pqrs->requester_email,
            'created_at' => $pqrs->created_at,
            'due_at' => $pqrs->due_at,
            'is_overdue' => $pqrs->isOverdue(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function attachment(PqrsAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name,
            'size' => $attachment->size,
            'mime_type' => $attachment->mime_type,
        ];
    }
}
