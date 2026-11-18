<?php

namespace App\Http\Controllers\Central;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * El super administrador corrige el nombre y el correo de los
 * administradores de una empresa (usuarios de la base de la empresa).
 */
class TenantAdminController extends Controller
{
    public function update(Request $request, Tenant $tenant, int $admin): RedirectResponse
    {
        // Los correos del personal se guardan en minúsculas.
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }

        // Todo dentro de la base de la empresa: ahí viven sus usuarios y ahí
        // se comprueba que el correo no lo use otra persona de la empresa.
        $name = $tenant->run(function () use ($request, $admin) {
            $user = User::where('role', UserRole::Admin)->findOrFail($admin);

            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            ], [], ['name' => 'nombre', 'email' => 'correo electrónico']);

            $user->update([
                'name' => trim($data['name']),
                'email' => $data['email'],
            ]);

            return $user->name;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Datos de {$name} actualizados."]);

        return back();
    }
}
