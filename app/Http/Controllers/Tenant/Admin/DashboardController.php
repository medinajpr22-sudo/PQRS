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

        // Se calcula en la base de datos (no se cargan miles de PQRS en memoria).
        $responded = $visible()->whereNotNull('responded_at');
        $respondedCount = (clone $responded)->count();
        $onTime = (clone $responded)
            ->where(fn ($q) => $q->whereNull('due_at')->orWhereColumn('responded_at', '<=', 'due_at'))
            ->count();
        $avgSeconds = $respondedCount > 0
            ? (float) (clone $responded)->selectRaw(self::secondsToRespondSql($responded->getModel()->getConnection()->getDriverName()))->value('seconds')
            : null;

        $byType = $visible()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $byStatus = $visible()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('tenant/admin/dashboard', [
            'stats' => [
                'total' => $visible()->count(),
                'open' => $visible()->open()->count(),
                'overdue' => $visible()->overdue()->count(),
                // Vencen de aquí al final del tercer día (los plazos vencen a las 23:59).
                'due_soon' => $visible()->open()->whereBetween('due_at', [now(), now()->addDays(3)->endOfDay()])->count(),
                'this_month' => $visible()->where('created_at', '>=', now()->startOfMonth())->count(),
                'on_time_rate' => $respondedCount > 0 ? round($onTime / $respondedCount * 100) : null,
                'avg_response_days' => $avgSeconds !== null ? round($avgSeconds / 86400, 1) : null,
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
            // Primero los reclamos de riesgo vital, luego por vencimiento.
            'urgent' => $visible()->open()
                ->with(['assignee:id,name', 'sede:id,name'])
                ->orderByRisk()
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

    /**
     * Promedio de segundos entre la radicación y la primera respuesta
     * (columna «seconds»), según el motor de base de datos.
     *
     * @return literal-string
     */
    private static function secondsToRespondSql(string $driver): string
    {
        return match ($driver) {
            'pgsql' => 'avg(extract(epoch from (responded_at - created_at))) as seconds',
            'mysql', 'mariadb' => 'avg(timestampdiff(second, created_at, responded_at)) as seconds',
            default => 'avg((julianday(responded_at) - julianday(created_at)) * 86400) as seconds',
        };
    }
}
