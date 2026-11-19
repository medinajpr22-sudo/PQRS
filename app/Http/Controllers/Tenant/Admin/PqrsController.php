<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\PqrsAttachment;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
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
            // "me" = Mis PQRS, "none" = sin responsable, o el id de un usuario
            'assigned' => is_string($request->query('assigned')) ? $request->query('assigned') : null,
            // id de una sede, o "none" = sin sede (solo administradores)
            'sede' => is_string($request->query('sede')) ? $request->query('sede') : null,
        ];

        $assigned = (string) $filters['assigned'];
        $sede = (string) $filters['sede'];
        $user = $request->user();

        $pqrs = Pqrs::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'sede:id,name'])
            ->when(ctype_digit($sede), fn ($q) => $q->where('sede_id', (int) $sede))
            ->when($sede === 'none', fn ($q) => $q->whereNull('sede_id'))
            ->when($assigned === 'me', fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($assigned === 'none', fn ($q) => $q->whereNull('assigned_to'))
            ->when(ctype_digit($assigned), fn ($q) => $q->where('assigned_to', (int) $assigned))
            ->when($filters['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->whereLike('radicado', "%{$filters['search']}%")
                ->orWhereLike('requester_name', "%{$filters['search']}%")
                ->orWhereLike('requester_email', "%{$filters['search']}%")
                ->orWhereLike('requester_document', "%{$filters['search']}%")
                ->orWhereLike('subject', "%{$filters['search']}%")))
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
            'staff' => self::staffOptions(),
            'sedes' => self::sedeFilterOptions($user),
            // Para las píldoras «Todas · Mis PQRS · Sin responsable».
            'counts' => [
                'all' => Pqrs::visibleTo($user)->count(),
                'mine' => Pqrs::visibleTo($user)->where('assigned_to', $user->id)->count(),
                'none' => Pqrs::visibleTo($user)->whereNull('assigned_to')->count(),
            ],
        ]);
    }

    public function show(Request $request, Pqrs $pqrs): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $types = collect($tenant->resolvedFormSchema()['types'])->keyBy('key');

        $pqrs->load(['activities.user', 'activities.attachments', 'attachments', 'assignee', 'sede']);

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
            // Solo los cambios de estado permitidos desde el estado actual.
            'transitions' => $pqrs->status->transitionOptions(),
            // Solo quienes pueden ver la sede de esta PQRS pueden ser responsables.
            'staff' => self::assignableStaff($pqrs->sede_id),
            'canAssignOthers' => $request->user()->isAdmin(),
            // Cualquier sede activa: si el paciente se equivocó, se traslada.
            'sedes' => Sede::active()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Sede $s) => ['value' => $s->id, 'label' => $s->name]),
        ]);
    }

    public function updateStatus(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(PqrsStatus::class)],
            'reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ], [], ['status' => 'estado', 'reason' => 'motivo']);

        $service->changeStatus($pqrs, PqrsStatus::from($data['status']), $request->user(), $data['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado actualizado.']);

        return back();
    }

    /**
     * Asigna la PQRS a un responsable (o se lo quita).
     */
    public function assign(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ], [], ['assigned_to' => 'responsable']);

        /** @var Tenant $tenant */
        $tenant = tenant();
        $assignee = isset($data['assigned_to']) ? User::find((int) $data['assigned_to']) : null;

        $service->assign($tenant, $pqrs, $assignee, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $assignee ? "Solicitud asignada a {$assignee->name}." : 'Se quitó el responsable.',
        ]);

        return back();
    }

    /**
     * Traslada la PQRS a otra sede (p. ej. el paciente eligió la sede equivocada).
     */
    public function changeSede(Request $request, Pqrs $pqrs, PqrsService $service): RedirectResponse
    {
        $data = $request->validate([
            'sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')],
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [], ['sede_id' => 'sede', 'reason' => 'motivo']);

        $sede = Sede::findOrFail((int) $data['sede_id']);
        $user = $request->user();

        $service->changeSede($pqrs, $sede, $user, $data['reason'] ?? null);

        // Si la trasladó a una sede que no atiende, ya no puede verla.
        if (! $user->canAccessSede($sede->id)) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "Solicitud {$pqrs->radicado} trasladada a {$sede->name}. Ya no la ves porque no atiendes esa sede.",
            ]);

            return to_route('tenant.admin.pqrs.index');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Solicitud trasladada a {$sede->name}."]);

        return back();
    }

    /**
     * Usuarios activos, para filtrar la bandeja por responsable.
     *
     * @return Collection<int, array{value: int, label: string}>
     */
    public static function staffOptions(): Collection
    {
        return User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name]);
    }

    /**
     * Usuarios activos que pueden ser responsables de una PQRS de esta sede:
     * los administradores y los agentes que atienden la sede.
     *
     * @return Collection<int, array{value: int, label: string}>
     */
    public static function assignableStaff(?int $sedeId): Collection
    {
        return User::where('is_active', true)
            ->with('sedes:id')
            ->orderBy('name')
            ->get(['id', 'name', 'role'])
            ->filter(fn (User $u) => $u->canAccessSede($sedeId))
            ->map(fn (User $u) => ['value' => $u->id, 'label' => $u->name])
            ->values();
    }

    /**
     * Sedes para filtrar la bandeja: todas (y «sin sede») para el
     * administrador; solo las suyas para un agente.
     *
     * @return Collection<int, array{value: string, label: string}>
     */
    public static function sedeFilterOptions(User $user): Collection
    {
        $ids = $user->accessibleSedeIds();

        $options = Sede::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Sede $s) => [
                'value' => (string) $s->id,
                'label' => $s->is_active ? $s->name : "{$s->name} (inactiva)",
            ]);

        if ($ids === null && Pqrs::whereNull('sede_id')->exists()) {
            $options->push(['value' => 'none', 'label' => 'Sin sede']);
        }

        return $options->values();
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
            'assignee' => $pqrs->assignee?->only(['id', 'name']),
            'sede' => $pqrs->sede?->only(['id', 'name']),
            'risk_level' => $pqrs->risk_level?->value,
            'risk_label' => $pqrs->risk_level?->label(),
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
