<?php

namespace App\Http\Controllers\Central;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\PqrsController as TenantPqrsController;
use App\Models\Pqrs;
use App\Models\PqrsDeletion;
use App\Models\Sede;
use App\Models\Tenant;
use App\Services\PqrsExport;
use App\Support\PqrsFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PQRS de las empresas, vistas desde el panel del super administrador.
 *
 * - La lista es de una empresa a la vez (cada empresa tiene su propia base
 *   de datos), con los mismos filtros que el panel de la empresa más la
 *   sede y el rango de fechas.
 * - La descarga en Excel puede ser de una empresa (con todos sus filtros)
 *   o de todas juntas (sin el filtro de sede: las sedes son de cada una).
 * - Eliminar es definitivo: se borran la PQRS, su historial y sus
 *   archivos. Por eso pide un motivo y queda registrado quién lo hizo
 *   (PqrsDeletion), sin guardar los datos del ciudadano.
 */
class PqrsController extends Controller
{
    /** @var array<string, string> */
    public const REASONS = [
        'spam' => 'Spam o radicación falsa',
        'duplicada' => 'Solicitud duplicada',
        'prueba' => 'Radicación de prueba',
        'otro' => 'Otro motivo',
    ];

    public function index(Request $request): Response
    {
        $tenants = self::tenants();
        $tenant = self::selectedTenant($request, $tenants);
        $filters = PqrsFilters::fromRequest($request);

        $data = $tenant?->run(function () use ($tenant, $filters) {
            $schema = $tenant->resolvedFormSchema();
            $types = collect(is_array($schema['types'] ?? null) ? $schema['types'] : [])->keyBy('key');

            return [
                'pqrs' => $filters->apply(Pqrs::query())
                    ->with(['assignee:id,name', 'sede:id,name'])
                    ->latest('id')
                    ->paginate(20)
                    ->withQueryString()
                    ->through(fn (Pqrs $p) => TenantPqrsController::listItem($p, $types)),
                'types' => $types->map(fn ($t) => ['value' => $t['key'], 'label' => $t['label']])->values(),
                'sedes' => Sede::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Sede $s) => ['value' => (string) $s->id, 'label' => $s->name]),
                'total' => Pqrs::query()->count(),
            ];
        });

        return Inertia::render('pqrs/index', [
            'companies' => array_map(fn (Tenant $t) => ['value' => $t->id, 'label' => $t->name], $tenants),
            'company' => $tenant?->id,
            'pqrs' => $data['pqrs'] ?? null,
            'total' => $data['total'] ?? 0,
            'types' => $data['types'] ?? [],
            'sedes' => $data['sedes'] ?? [],
            'filters' => $filters->toArray(),
            'statuses' => PqrsStatus::options(),
            'reasons' => collect(self::REASONS)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values(),
            'deletions' => $tenant === null ? [] : PqrsDeletion::query()
                ->where('tenant_id', $tenant->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (PqrsDeletion $d) => [
                    'id' => $d->id,
                    'radicado' => $d->radicado,
                    'reason' => self::REASONS[$d->reason] ?? $d->reason,
                    'detail' => $d->detail,
                    'deleted_by' => $d->deleted_by_name,
                    'deleted_at' => $d->created_at,
                ]),
        ]);
    }

    /**
     * Excel de una empresa (?empresa=id) o de todas (?empresa=todas).
     */
    public function export(Request $request, PqrsExport $export): StreamedResponse
    {
        $tenants = self::tenants();
        $filters = PqrsFilters::fromRequest($request);
        $all = $request->query('empresa') === 'todas';

        if ($all) {
            $selected = $tenants;
            $filters = $filters->withoutSede();
            $name = 'PQRS todas las empresas';
        } else {
            // Una empresa que no existe (un enlace mal copiado) no descarga
            // por error las PQRS de otra: responde 404.
            $tenant = collect($tenants)->first(fn (Tenant $t) => $t->id === $request->query('empresa')) ?? abort(404);
            $selected = [$tenant];
            $name = 'PQRS '.Str::slug($tenant->name);
        }

        Log::notice('PQRS descargadas en Excel (super administrador)', [
            'empresas' => $all ? 'todas' : $selected[0]->id,
            'usuario' => $request->user()->id,
            'filtros' => $filters->toArray(),
        ]);

        return $export->download($selected, $filters, $name.' '.now()->format('Y-m-d').'.xlsx');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'empresa' => ['required', 'string', Rule::exists('tenants', 'id')],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
            'reason' => ['required', Rule::in(array_keys(self::REASONS))],
            'detail' => ['nullable', 'required_if:reason,otro', 'string', 'max:300'],
        ], [
            'ids.required' => 'Selecciona al menos una PQRS.',
            'detail.required_if' => 'Cuenta brevemente el motivo.',
        ], [
            'reason' => 'motivo',
            'detail' => 'detalle',
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($data['empresa']);
        $user = $request->user();

        // Fuera de la empresa su conexión ya no existe: lo que se necesita
        // después se copia aquí como datos simples.
        $deleted = $tenant->run(function () use ($data): array {
            $items = DB::transaction(function () use ($data): array {
                $items = Pqrs::query()
                    ->whereKey($data['ids'])
                    ->lockForUpdate()
                    ->get(['id', 'radicado', 'type', 'status', 'created_at']);

                // El historial y los adjuntos se borran con ella (cascada).
                Pqrs::query()->whereKey($items->modelKeys())->delete();

                return $items->map(fn (Pqrs $p) => [
                    'radicado' => $p->radicado,
                    'type' => $p->type,
                    'status' => $p->status->value,
                    'filed_at' => $p->created_at,
                ])->all();
            });

            foreach ($items as $item) {
                Storage::disk('local')->deleteDirectory("pqrs/{$item['radicado']}");
            }

            return $items;
        });

        foreach ($deleted as $item) {
            PqrsDeletion::query()->create([
                ...$item,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'reason' => $data['reason'],
                'detail' => $data['detail'] ?? null,
                'deleted_by' => $user->id,
                'deleted_by_name' => $user->name,
            ]);
        }

        Log::notice('PQRS eliminadas por un super administrador', [
            'empresa' => $tenant->id,
            'radicados' => array_column($deleted, 'radicado'),
            'motivo' => $data['reason'],
            'usuario' => $user->id,
        ]);

        $count = count($deleted);

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($count) {
            0 => 'Esas PQRS ya no existían.',
            1 => "Se eliminó la PQRS {$deleted[0]['radicado']}.",
            default => "Se eliminaron {$count} PQRS.",
        }]);

        return back();
    }

    /**
     * Todas las empresas, por nombre.
     *
     * @return list<Tenant>
     */
    private static function tenants(): array
    {
        $tenants = [];

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            if ($tenant instanceof Tenant) {
                $tenants[] = $tenant;
            }
        }

        return $tenants;
    }

    /**
     * La empresa elegida (?empresa=id), o la primera de la lista.
     *
     * @param  list<Tenant>  $tenants
     */
    private static function selectedTenant(Request $request, array $tenants): ?Tenant
    {
        $id = $request->query('empresa');

        foreach ($tenants as $tenant) {
            if ($tenant->id === $id) {
                return $tenant;
            }
        }

        return $tenants[0] ?? null;
    }
}
