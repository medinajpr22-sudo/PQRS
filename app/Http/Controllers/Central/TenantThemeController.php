<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\UpdateTenantThemeRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class TenantThemeController extends Controller
{
    public function update(UpdateTenantThemeRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo', 'icon', 'remove_icon']);
        $theme = $tenant->resolvedTheme();
        $old = ['logo' => $theme['logo_path'], 'icon' => $theme['icon_path'] ?? null];
        $new = [];

        // Primero se guardan los archivos nuevos y el diseño; los viejos se
        // borran solo al final. Si algo falla a mitad de camino, el portal
        // sigue mostrando la imagen anterior (y no queda un archivo suelto).
        try {
            foreach (['logo', 'icon'] as $field) {
                $new[$field] = $this->newImagePath($request, $tenant, $field, $old[$field]);
            }

            $tenant->theme = [
                ...collect($theme)->except(['logo_url', 'icon_url', 'font_family', 'font_url'])->all(),
                ...$data,
                'footer_text' => $data['footer_text'] ?? '',
                'website_url' => $data['website_url'] ?? '',
                'hero_subtitle' => $data['hero_subtitle'] ?? '',
                // Logo: encabezado del portal, panel y correos.
                'logo_path' => $new['logo'],
                // Ícono: solo la pestaña del navegador y el acceso directo del celular.
                'icon_path' => $new['icon'],
            ];
            $tenant->save();
        } catch (Throwable $e) {
            foreach ($new as $field => $path) {
                if ($path !== null && $path !== $old[$field]) {
                    Storage::disk('public')->delete($path);
                }
            }

            throw $e;
        }

        foreach ($old as $field => $path) {
            if ($path !== null && $path !== $new[$field]) {
                Storage::disk('public')->delete($path);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño del portal actualizado.']);

        return back();
    }

    /**
     * Ruta que queda para una imagen de la marca (campos «logo»/«remove_logo»
     * o «icon»/«remove_icon»): la nueva si se subió, null si se quitó, o la
     * actual. No borra nada: eso se hace cuando el diseño ya quedó guardado.
     */
    private function newImagePath(UpdateTenantThemeRequest $request, Tenant $tenant, string $field, ?string $currentPath): ?string
    {
        $file = $request->file($field);

        if (! $file) {
            return $request->boolean("remove_{$field}") ? null : $currentPath;
        }

        // Disco público central: se sirve en /storage/... desde cualquier dominio.
        $stored = $file->store("tenants/{$tenant->id}", 'public');

        if ($stored === false) {
            throw ValidationException::withMessages([
                $field => 'No se pudo guardar la imagen. Inténtalo de nuevo.',
            ]);
        }

        return $stored;
    }
}
