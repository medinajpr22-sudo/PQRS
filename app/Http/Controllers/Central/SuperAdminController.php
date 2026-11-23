<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super administradores: las personas que entran al panel central.
 *
 * Todos los usuarios de la base central son super administradores y tienen
 * los mismos permisos: crear, editar y eliminar empresas y a otros super
 * administradores. Por eso estas rutas piden confirmar la contraseña.
 *
 * Siempre queda al menos uno: nadie se elimina a sí mismo desde aquí (para
 * eso está Ajustes → Perfil, que también lo impide si es el último), y dos
 * eliminaciones al mismo tiempo no pueden dejar el panel sin nadie.
 */
class SuperAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $admins = User::query()
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'two_factor' => $u->two_factor_confirmed_at !== null,
                'created_at' => $u->created_at,
                'is_me' => $u->is($request->user()),
            ]);

        return Inertia::render('super-admins/index', ['admins' => $admins]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $admin = new User([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'password' => (string) $data['password'],
        ]);
        // Lo crea otro super administrador: no hace falta verificar el correo.
        $admin->forceFill(['email_verified_at' => now()])->save();

        Log::notice('Super administrador creado', ['id' => $admin->id, 'email' => $admin->email, 'por' => $request->user()->id]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$admin->name} ya es super administrador. Compártele la contraseña inicial por un medio seguro."]);

        return to_route('super-admins.index');
    }

    public function update(Request $request, User $superAdmin): RedirectResponse
    {
        $isMe = $superAdmin->is($request->user());
        $data = $this->validated($request, $superAdmin);

        $superAdmin->fill(['name' => (string) $data['name'], 'email' => (string) $data['email']]);

        if ($superAdmin->isDirty('email')) {
            $superAdmin->forceFill(['email_verified_at' => now()]);
        }

        // La contraseña propia se cambia en Ajustes → Seguridad, que pide la
        // actual. Cambiar la de otro es recuperar su cuenta (la olvidó, perdió
        // el celular o alguien más entró): se cierran sus sesiones y se quitan
        // su verificación en dos pasos y sus llaves de acceso, que deberá
        // configurar de nuevo. Así no queda una llave puesta por un intruso.
        $newPassword = ! $isMe && filled($data['password'] ?? null) ? (string) $data['password'] : null;

        if ($newPassword !== null) {
            $superAdmin->password = $newPassword;
            $superAdmin->setRememberToken(Str::random(60));
            $superAdmin->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ]);
        }

        DB::transaction(function () use ($superAdmin, $newPassword): void {
            $superAdmin->save();

            if ($newPassword !== null) {
                $superAdmin->passkeys()->delete();
            }
        });

        if ($newPassword !== null) {
            UserSessions::end($superAdmin);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $newPassword !== null
            ? "Datos de {$superAdmin->name} actualizados. Su contraseña cambió: se cerraron sus sesiones y deberá configurar de nuevo la verificación en dos pasos y sus llaves de acceso."
            : "Datos de {$superAdmin->name} actualizados."]);

        return to_route('super-admins.index');
    }

    public function destroy(Request $request, User $superAdmin): RedirectResponse
    {
        $me = $request->user();

        if ($superAdmin->is($me)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No puedes eliminarte a ti mismo. Pídeselo a otro super administrador.']);

            return to_route('super-admins.index');
        }

        // Bloquea las dos cuentas (en orden, para no trabarse): si otro
        // super administrador acaba de eliminar a quien hace esto, no sigue.
        $deleted = DB::transaction(function () use ($me, $superAdmin): bool {
            $locked = User::query()
                ->whereKey([$me->id, $superAdmin->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');

            abort_unless($locked->contains($me->id), 403);

            return $locked->contains($superAdmin->id) && (bool) $superAdmin->delete();
        });

        if ($deleted) {
            UserSessions::end($superAdmin);
            DB::table('password_reset_tokens')->where('email', $superAdmin->email)->delete();

            Log::notice('Super administrador eliminado', ['id' => $superAdmin->id, 'email' => $superAdmin->email, 'por' => $me->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$superAdmin->name} ya no es super administrador."]);

        return to_route('super-admins.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $admin = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($admin)],
            'password' => [$admin === null ? 'required' : 'nullable', 'string', Password::default()],
        ], [
            'email.unique' => 'Ya hay un super administrador con este correo.',
        ], [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
        ]);
    }
}
