<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $types = collect($tenant->resolvedFormSchema()['types'])->keyBy('key');

        $responded = Pqrs::whereNotNull('responded_at')->get(['created_at', 'responded_at', 'due_at']);
        $onTime = $responded->filter(fn (Pqrs $p) => $p->due_at === null || $p->responded_at->lte($p->due_at))->count();

        $byType = Pqrs::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');
        $byStatus = Pqrs::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('tenant/admin/dashboard', [
            'stats' => [
                'total' => Pqrs::count(),
                'open' => Pqrs::open()->count(),
                'overdue' => Pqrs::overdue()->count(),
                'due_soon' => Pqrs::open()->whereBetween('due_at', [now(), now()->addDays(3)])->count(),
                'this_month' => Pqrs::where('created_at', '>=', now()->startOfMonth())->count(),
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
            'urgent' => Pqrs::open()
                ->with('assignee:id,name')
                ->orderBy('due_at')
                ->limit(6)
                ->get()
                ->map(fn (Pqrs $p) => PqrsController::listItem($p, $types)),
        ]);
    }
}
