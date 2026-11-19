<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateTenantThemeRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantThemeController extends Controller
{
    public function update(UpdateTenantThemeRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo', 'icon', 'remove_icon']);
        $theme = $tenant->resolvedTheme();

        $tenant->theme = [
            ...collect($theme)->except(['logo_url', 'icon_url', 'font_family', 'font_url'])->all(),
            ...$data,
            'footer_text' => $data['footer_text'] ?? '',
            'website_url' => $data['website_url'] ?? '',
            'hero_subtitle' => $data['hero_subtitle'] ?? '',
            // Logo: encabezado del portal, panel y correos.
            'logo_path' => $this->replaceImage($request, $tenant, 'logo', $theme['logo_path']),
            // Ícono: solo la pestaña del navegador y el acceso directo del celular.
            'icon_path' => $this->replaceImage($request, $tenant, 'icon', $theme['icon_path'] ?? null),
        ];
        $tenant->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño del portal actualizado.']);

        return back();
    }

    /**
     * Sube, cambia o quita una imagen de la marca (campos «logo»/«remove_logo»
     * o «icon»/«remove_icon») y devuelve la ruta que queda guardada.
     */
    private function replaceImage(UpdateTenantThemeRequest $request, Tenant $tenant, string $field, ?string $currentPath): ?string
    {
        $removing = $request->boolean("remove_{$field}");
        $file = $request->file($field);

        if (($removing || $file) && $currentPath) {
            Storage::disk('public')->delete($currentPath);
            $currentPath = null;
        }

        if ($file) {
            // Disco público central: se sirve en /storage/... desde cualquier dominio.
            $stored = $file->store("tenants/{$tenant->id}", 'public');

            if ($stored === false) {
                throw ValidationException::withMessages([
                    $field => 'No se pudo guardar la imagen. Inténtalo de nuevo.',
                ]);
            }

            $currentPath = $stored;
        }

        return $currentPath;
    }
}
