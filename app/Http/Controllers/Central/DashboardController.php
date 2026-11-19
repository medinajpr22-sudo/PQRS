<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Pqrs;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Resumen global de la plataforma: empresas y PQRS de todas las bases de datos.
     */
    public function __invoke(): Response
    {
        $tenants = Tenant::with('domains')->orderBy('name')->get();
        $months = collect(range(5, 0))->map(fn (int $i) => now()->startOfMonth()->subMonths($i));

        $empty = [
            'stats' => ['total' => 0, 'open' => 0, 'overdue' => 0, 'this_month' => 0],
            'by_type' => [],
            'monthly' => $months->map(fn () => 0)->all(),
        ];

        $rows = $tenants->map(function (Tenant $tenant) use ($months, $empty) {
            // Si la base de una empresa no responde, el resumen se muestra igual
            // (con ceros para esa empresa) y el error queda en el registro.
            $data = rescue(fn () => $tenant->run(fn () => [
                'stats' => [
                    'total' => Pqrs::count(),
                    'open' => Pqrs::open()->count(),
                    'overdue' => Pqrs::overdue()->count(),
                    'this_month' => Pqrs::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'by_type' => Pqrs::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type')->all(),
                'monthly' => $months->map(fn (CarbonInterface $m) => Pqrs::whereBetween('created_at', [$m, $m->endOfMonth()])->count())->all(),
            ]), $empty);

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'plan' => $tenant->plan,
                'layout' => $tenant->resolvedTheme()['layout'],
                'primary_color' => $tenant->resolvedTheme()['primary_color'],
                'logo_url' => $tenant->resolvedTheme()['logo_url'],
                'portal_url' => $tenant->portalUrl(),
                ...$data,
            ];
        });

        $byType = [];
        foreach ($rows as $row) {
            foreach ($row['by_type'] as $type => $total) {
                $byType[$type] = ($byType[$type] ?? 0) + $total;
            }
        }

        return Inertia::render('dashboard', [
            'totals' => [
                'tenants' => $tenants->count(),
                'active_tenants' => $tenants->where('status', Tenant::STATUS_ACTIVE)->count(),
                'pqrs' => $rows->sum('stats.total'),
                'open' => $rows->sum('stats.open'),
                'overdue' => $rows->sum('stats.overdue'),
                'this_month' => $rows->sum('stats.this_month'),
            ],
            'months' => $months->map(fn (CarbonInterface $m) => $m->format('Y-m'))->all(),
            'monthly' => $months->keys()->map(fn (int $i) => $rows->sum(fn ($r) => $r['monthly'][$i]))->all(),
            'byType' => $byType,
            'tenants' => $rows->map(fn ($r) => collect($r)->except(['by_type', 'monthly'])->all())->values(),
        ]);
    }
}
