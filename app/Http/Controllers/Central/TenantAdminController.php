<?php

namespace App\Http\Controllers\Central;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * El super administrador corrige el nombre, el correo y la contraseña de los
 * administradores de una empresa (usuarios de la base de la empresa): por
 * ejemplo, cuando el administrador olvidó su contraseña o se fue de la
 * empresa y entra otra persona.
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
        [$name, $passwordChanged] = $tenant->run(function () use ($request, $admin) {
            $user = User::where('role', UserRole::Admin)->findOrFail($admin);

            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
                // Opcional: vacía, la contraseña no cambia.
                'password' => ['nullable', 'string', Password::default()],
            ], [], ['name' => 'nombre', 'email' => 'correo electrónico', 'password' => 'contraseña']);

            $passwordChanged = filled($data['password'] ?? null);

            DB::transaction(function () use ($user, $data, $passwordChanged): void {
                $user->fill([
                    'name' => trim($data['name']),
                    'email' => $data['email'],
                ]);

                if ($passwordChanged) {
                    // Con la contraseña nueva se cierran sus sesiones abiertas en
                    // el panel de la empresa (y se anula su «Recordarme»).
                    $user->password = $data['password'];
                    $user->setRememberToken(Str::random(60));
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                }

                $user->save();
            });

            return [$user->name, $passwordChanged];
        });

        if ($passwordChanged) {
            // Queda registrado quién cambió la contraseña de quién (nunca la contraseña).
            Log::notice('Super administrador cambió la contraseña de un administrador de empresa', [
                'empresa' => $tenant->id,
                'administrador' => $admin,
                'super_administrador' => $request->user()?->id,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $passwordChanged
            ? "Datos de {$name} actualizados. Su contraseña cambió y se cerraron sus sesiones: compártesela por un medio seguro."
            : "Datos de {$name} actualizados."]);

        return back();
    }
}
