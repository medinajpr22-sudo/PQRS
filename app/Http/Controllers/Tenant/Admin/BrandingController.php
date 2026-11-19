<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Colores de la empresa que puede ajustar su propio administrador: los del
 * portal público y los del panel administrativo. El resto del diseño
 * (plantilla, logo, textos) lo sigue manejando el super admin.
 */
class BrandingController extends Controller
{
    private const FIELDS = ['primary_color', 'accent_color', 'panel_color', 'panel_accent'];

    public function edit(): Response
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $theme = $tenant->resolvedTheme();

        return Inertia::render('tenant/admin/colors', [
            'colors' => collect(self::FIELDS)->mapWithKeys(fn (string $key) => [$key => $theme[$key]]),
            'layout' => $theme['layout'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        $data = $request->validate(
            array_fill_keys(self::FIELDS, $hex),
            [],
            [
                'primary_color' => 'color principal',
                'accent_color' => 'color de acento',
                'panel_color' => 'color del menú',
                'panel_accent' => 'color de botones',
            ],
        );

        /** @var Tenant $tenant */
        $tenant = tenant();
        $tenant->theme = [
            ...collect($tenant->resolvedTheme())->except(['logo_url', 'font_family', 'font_url'])->all(),
            ...array_map('strtolower', $data),
        ];
        $tenant->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Colores de la empresa actualizados.']);

        return back();
    }
}
