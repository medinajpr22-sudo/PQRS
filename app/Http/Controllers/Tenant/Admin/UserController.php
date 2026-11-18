<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Sede;
use App\Models\User;
use App\Services\PqrsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestión de usuarios de la empresa (solo el administrador de empresa).
 *
 * Los agentes solo ven las PQRS de las sedes que se les asignan aquí; el
 * administrador puede cambiarlas en cualquier momento.
 *
 * Los usuarios no se borran: se desactivan, para conservar quién hizo
 * cada actuación en el historial de las PQRS.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('sedes:id,name')
            ->withCount(['assignedPqrs as open_assigned' => fn ($q) => $q->whereIn('status', PqrsStatus::openValues())])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->value,
                'role_label' => $u->role?->label(),
                'is_active' => $u->is_active,
                'sedes' => $u->sedes->map(fn (Sede $s) => ['id' => $s->id, 'name' => $s->name])->values(),
                'open_assigned' => (int) $u->getAttribute('open_assigned'),
                'last_login_at' => $u->last_login_at,
                'created_at' => $u->created_at,
                'is_me' => $u->id === $request->user()->id,
            ]);

        return Inertia::render('tenant/admin/users/index', [
            'users' => $users,
            'roles' => UserRole::options(),
            'sedes' => SedeController::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        self::normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'sedes' => ['nullable', 'array'],
            'sedes.*' => ['integer', Rule::exists('sedes', 'id')],
            'password' => ['required', 'string', Password::default()],
        ], [], self::attributes());

        $role = UserRole::from($data['role']);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        DB::transaction(function () use ($user, $role, $data) {
            $user->forceFill([
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();

            $user->sedes()->sync(self::sedesFor($role, $data));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$user->name} creado."]);

        return back();
    }

    public function update(Request $request, User $user, PqrsService $service): RedirectResponse
    {
        self::normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
            'sedes' => ['nullable', 'array'],
            'sedes.*' => ['integer', Rule::exists('sedes', 'id')],
            'password' => ['nullable', 'string', Password::default()],
        ], [], self::attributes());

        $role = UserRole::from($data['role']);
        $isActive = (bool) $data['is_active'];
        $isMe = $user->id === $request->user()->id;

        if ($isMe && ($role !== $user->role || ! $isActive)) {
            throw ValidationException::withMessages([
                'role' => 'No puedes cambiar tu propio rol ni desactivarte. Pídeselo a otro administrador.',
            ]);
        }

        $stopsBeingActiveAdmin = $user->isAdmin() && $user->is_active && ($role !== UserRole::Admin || ! $isActive);

        if ($stopsBeingActiveAdmin && User::where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'La empresa debe tener al menos un administrador activo.',
            ]);
        }

        $deactivated = $user->is_active && ! $isActive;
        $passwordChanged = filled($data['password'] ?? null);
        $currentSessionId = $request->session()->getId();

        $by = $request->user();

        $released = DB::transaction(function () use ($user, $data, $role, $isActive, $deactivated, $passwordChanged, $stopsBeingActiveAdmin, $currentSessionId, $service, $by) {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if ($passwordChanged) {
                $user->password = $data['password'];
            }

            $user->forceFill(['role' => $role, 'is_active' => $isActive]);

            if ($deactivated || $passwordChanged) {
                // Cierra sus sesiones abiertas (menos la de quien hace el cambio)
                // y anula el "Recordarme": una contraseña nueva o una cuenta
                // desactivada no deben dejar sesiones vivas.
                $user->setRememberToken(Str::random(60));
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }

            $user->save();

            // Se vuelve a contar después de escribir: si dos administradores se
            // quitan el rol al mismo tiempo, el segundo ve el cambio del primero
            // y su transacción se revierte.
            if ($stopsBeingActiveAdmin && ! User::where('role', UserRole::Admin)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([
                    'role' => 'La empresa debe tener al menos un administrador activo.',
                ]);
            }

            $user->sedes()->sync(self::sedesFor($role, $data));

            // Si perdió acceso a una sede, sus PQRS abiertas de esa sede quedan
            // sin responsable para que alguien que sí la atienda las tome.
            return $service->releaseInaccessibleAssignments($user, $by);
        });

        $message = "Usuario {$user->name} actualizado.";

        if ($released > 0) {
            $message .= " {$released} ".($released === 1 ? 'solicitud quedó' : 'solicitudes quedaron').' sin responsable porque ya no tiene acceso a su sede.';
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    /**
     * Sedes que se guardan para el usuario. Los administradores ven todas,
     * así que no se les asigna ninguna.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    protected static function sedesFor(UserRole $role, array $data): array
    {
        if ($role === UserRole::Admin) {
            return [];
        }

        return array_values(array_unique(array_map('intval', (array) ($data['sedes'] ?? []))));
    }

    /**
     * Los correos se guardan en minúsculas. Se normalizan antes de validar para
     * que la regla «unique» compare exactamente lo que se va a guardar.
     */
    protected static function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, string>
     */
    protected static function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
            'is_active' => 'estado',
            'sedes' => 'sedes',
            'sedes.*' => 'sede',
            'password' => 'contraseña',
        ];
    }
}
