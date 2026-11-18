<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen del panel de la empresa.
 *
 * El administrador ve las cifras de todas las sedes; un agente, solo las de
 * las sedes que tiene asignadas.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $types = collect($tenant->resolvedFormSchema()['types'])->keyBy('key');
        $user = $request->user();

        /** @return Builder<Pqrs> */
        $visible = fn (): Builder => Pqrs::query()->visibleTo($user);

        $responded = $visible()->whereNotNull('responded_at')->get(['created_at', 'responded_at', 'due_at']);
        $onTime = $responded->filter(fn (Pqrs $p) => $p->due_at === null || $p->responded_at->lte($p->due_at))->count();

        $byType = $visible()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $byStatus = $visible()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('tenant/admin/dashboard', [
            'stats' => [
                'total' => $visible()->count(),
                'open' => $visible()->open()->count(),
                'overdue' => $visible()->overdue()->count(),
                'due_soon' => $visible()->open()->whereBetween('due_at', [now(), now()->addDays(3)])->count(),
                'this_month' => $visible()->where('created_at', '>=', now()->startOfMonth())->count(),
                'on_time_rate' => $responded->count() > 0 ? round($onTime / $responded->count() * 100) : null,
                'avg_response_days' => $responded->count() > 0
                    ? round($responded->avg(fn (Pqrs $p) => $p->created_at->diffInHours($p->responded_at) / 24), 1)
                    : null,
            ],
            'byType' => $types->map(fn ($t) => [
                'key' => $t['key'],
                'label' => $t['label'],
                'total' => (int) ($byType[$t['key']] ?? 0),
            ])->filter(fn ($t) => $t['total'] > 0 || $types[$t['key']]['enabled'])->values(),
            'byStatus' => collect(PqrsStatus::cases())->map(fn (PqrsStatus $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($byStatus[$s->value] ?? 0),
            ]),
            'bySede' => self::bySede($user),
            'urgent' => $visible()->open()
                ->with(['assignee:id,name', 'sede:id,name'])
                ->orderBy('due_at')
                ->limit(6)
                ->get()
                ->map(fn (Pqrs $p) => PqrsController::listItem($p, $types)),
        ]);
    }

    /**
     * Totales por sede (solo las sedes que el usuario puede ver).
     *
     * @return list<array{id: int|null, name: string, total: int, open: int, overdue: int}>
     */
    protected static function bySede(User $user): array
    {
        $ids = $user->accessibleSedeIds();
        $open = PqrsStatus::openValues();
        $in = implode(', ', array_fill(0, count($open), '?'));

        $rows = Pqrs::query()
            ->visibleTo($user)
            ->selectRaw('sede_id, count(*) as total')
            ->selectRaw("sum(case when status in ({$in}) then 1 else 0 end) as open", $open)
            ->selectRaw("sum(case when status in ({$in}) and due_at < ? then 1 else 0 end) as overdue", [...$open, now()])
            ->groupBy('sede_id')
            ->get()
            ->keyBy(fn (Pqrs $row) => (string) $row->sede_id);

        $result = array_values(Sede::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->filter(fn (Sede $s) => $s->is_active || $rows->has((string) $s->id))
            ->map(fn (Sede $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'total' => (int) $rows->get((string) $s->id)?->getAttribute('total'),
                'open' => (int) $rows->get((string) $s->id)?->getAttribute('open'),
                'overdue' => (int) $rows->get((string) $s->id)?->getAttribute('overdue'),
            ])
            ->all());

        // PQRS radicadas antes de crear sedes: solo las ve el administrador.
        if ($ids === null && $rows->has('')) {
            $result[] = [
                'id' => null,
                'name' => 'Sin sede',
                'total' => (int) $rows['']->getAttribute('total'),
                'open' => (int) $rows['']->getAttribute('open'),
                'overdue' => (int) $rows['']->getAttribute('overdue'),
            ];
        }

        return $result;
    }
}
