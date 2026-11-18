<?php

namespace App\Http\Middleware;

use App\Models\Pqrs;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = tenant();

        return [
            ...parent::share($request),
            'name' => $tenant instanceof Tenant ? $tenant->name : config('app.name'),
            'tenant' => $tenant instanceof Tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'theme' => $tenant->resolvedTheme(),
            ] : null,
            'auth' => [
                'user' => $request->user(),
            ],
            // Dónde se está: panel central o una empresa. (Ojo: algunas páginas
            // centrales reciben su propio prop «tenant» con la empresa editada.)
            'context' => $tenant instanceof Tenant ? 'tenant' : 'central',
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Campana del panel de la empresa: PQRS abiertas asignadas a mí.
            'myOpenPqrs' => fn () => $tenant instanceof Tenant && $request->user()
                ? Pqrs::open()->where('assigned_to', $request->user()->id)->count()
                : null,
        ];
    }
}
