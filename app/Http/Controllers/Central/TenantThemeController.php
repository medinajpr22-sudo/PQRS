<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateTenantThemeRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TenantThemeController extends Controller
{
    public function update(UpdateTenantThemeRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);
        $theme = $tenant->resolvedTheme();
        $logoPath = $theme['logo_path'];

        if ($request->boolean('remove_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            $logoPath = null;
        }

        if ($request->hasFile('logo')) {
            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            // Disco público central: se sirve en /storage/... desde cualquier dominio.
            $logoPath = $request->file('logo')->store("tenants/{$tenant->id}", 'public');
        }

        $tenant->theme = [
            ...collect($theme)->except(['logo_url', 'font_family', 'font_url'])->all(),
            ...$data,
            'footer_text' => $data['footer_text'] ?? '',
            'website_url' => $data['website_url'] ?? '',
            'hero_subtitle' => $data['hero_subtitle'] ?? '',
            'logo_path' => $logoPath,
        ];
        $tenant->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño del portal actualizado.']);

        return back();
    }
}
