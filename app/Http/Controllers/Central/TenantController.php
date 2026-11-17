<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreTenantRequest;
use App\Http\Requests\Central\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use App\Support\TenantDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status');

        $tenants = Tenant::query()
            ->with('domains')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%")
                ->orWhere('nit', 'like', "%{$search}%")))
            ->when(in_array($status, [Tenant::STATUS_ACTIVE, Tenant::STATUS_SUSPENDED], true), fn ($q) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Tenant $tenant) => $this->summary($tenant));

        return Inertia::render('tenants/index', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tenants/create', [
            'plans' => Tenant::PLANS,
            'baseDomain' => config('tenancy.central_domains')[0],
        ]);
    }

    public function store(StoreTenantRequest $request, TenantProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validated();

        $tenant = $provisioner->create([
            ...$data,
            'id' => $data['subdomain'],
            'domain' => Tenant::subdomainToDomain($data['subdomain']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Empresa «{$tenant->name}» creada. Ahora personaliza su diseño y formulario."]);

        return to_route('tenants.edit', $tenant);
    }

    public function edit(Tenant $tenant): Response
    {
        $tenant->load('domains');

        return Inertia::render('tenants/edit', [
            'tenant' => [
                ...$this->summary($tenant),
                'theme' => $tenant->resolvedTheme(),
                'form_schema' => $tenant->resolvedFormSchema(),
                'stats' => $tenant->pqrsStats(),
            ],
            'plans' => Tenant::PLANS,
            'layouts' => TenantDefaults::LAYOUTS,
            'fonts' => collect(TenantDefaults::FONTS)->map(fn ($f, $key) => ['value' => $key, 'label' => $f['label'], 'family' => $f['family'], 'bunny' => $f['bunny']])->values(),
            'fieldTypes' => TenantDefaults::FIELD_TYPES,
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();

        $tenant->update(collect($data)->except('domains')->all());

        // Sincronizar dominios
        $tenant->domains()->whereNotIn('domain', $data['domains'])->delete();
        foreach ($data['domains'] as $domain) {
            $tenant->domains()->firstOrCreate(['domain' => $domain]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos de la empresa actualizados.']);

        return back();
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($request->input('confirmation') !== $tenant->id) {
            throw ValidationException::withMessages([
                'confirmation' => "Escribe «{$tenant->id}» para confirmar.",
            ]);
        }

        $name = $tenant->name;
        $storageDir = storage_path(config('tenancy.filesystem.suffix_base').$tenant->id);

        // Elimina el registro, sus dominios y su base de datos (TenantDeleted → DeleteDatabase).
        $tenant->delete();

        Storage::disk('public')->deleteDirectory("tenants/{$tenant->id}");
        File::deleteDirectory($storageDir);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Empresa «{$name}» eliminada."]);

        return to_route('tenants.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Tenant $tenant): array
    {
        $theme = $tenant->resolvedTheme();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'nit' => $tenant->nit,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'status' => $tenant->status,
            'plan' => $tenant->plan,
            'radicado_prefix' => $tenant->radicado_prefix,
            'domains' => $tenant->domains->pluck('domain')->values(),
            'portal_url' => $tenant->portalUrl(),
            'logo_url' => $theme['logo_url'],
            'primary_color' => $theme['primary_color'],
            'layout' => $theme['layout'],
            'created_at' => $tenant->created_at,
        ];
    }
}
