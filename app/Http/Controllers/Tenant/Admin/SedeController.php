<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Http\Controllers\Controller;
use App\Models\Sede;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sedes de la empresa (solo el administrador de empresa).
 *
 * Las sedes no se borran: se desactivan. Una sede inactiva ya no aparece
 * en el formulario público, pero sus PQRS y sus agentes se conservan.
 */
class SedeController extends Controller
{
    public function index(): Response
    {
        $sedes = Sede::query()
            ->withCount([
                'users as agents_count' => fn ($q) => $q->where('is_active', true),
                'pqrs as open_pqrs_count' => fn ($q) => $q->whereIn('status', PqrsStatus::openValues()),
                'pqrs as total_pqrs_count',
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Sede $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => $s->is_active,
                'agents_count' => (int) $s->getAttribute('agents_count'),
                'open_pqrs_count' => (int) $s->getAttribute('open_pqrs_count'),
                'total_pqrs_count' => (int) $s->getAttribute('total_pqrs_count'),
            ]);

        return Inertia::render('tenant/admin/sedes/index', [
            'sedes' => $sedes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sedes', 'name')],
        ], [], ['name' => 'nombre']);

        $sede = Sede::create(['name' => trim($data['name']), 'is_active' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sede {$sede->name} creada. Asígnala a los agentes que la atienden."]);

        return back();
    }

    public function update(Request $request, Sede $sede): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('sedes', 'name')->ignore($sede->id)],
            'is_active' => ['required', 'boolean'],
        ], [], ['name' => 'nombre', 'is_active' => 'estado']);

        $sede->update([
            'name' => trim($data['name']),
            'is_active' => (bool) $data['is_active'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Sede {$sede->name} actualizada."]);

        return back();
    }

    /**
     * Sedes para elegir al crear o editar un agente.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function options(): array
    {
        return array_values(Sede::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (Sede $s) => [
                'value' => $s->id,
                'label' => $s->is_active ? $s->name : "{$s->name} (inactiva)",
            ])
            ->all());
    }
}
