<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
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
 * Gestión de funcionarios de la empresa (solo administradores).
 *
 * Los usuarios no se borran: se desactivan, para conservar quién hizo
 * cada actuación en el historial de las PQRS.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
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
                'open_assigned' => $u->open_assigned,
                'created_at' => $u->created_at,
                'is_me' => $u->id === $request->user()->id,
            ]);

        return Inertia::render('tenant/admin/users/index', [
            'users' => $users,
            'roles' => UserRole::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        self::normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => ['required', 'string', Password::default()],
        ], [], self::attributes());

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $user->forceFill([
            'role' => UserRole::from($data['role']),
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$user->name} creado."]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        self::normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'is_active' => ['required', 'boolean'],
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

        DB::transaction(function () use ($user, $data, $role, $isActive, $deactivated, $passwordChanged, $stopsBeingActiveAdmin, $currentSessionId) {
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
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Usuario {$user->name} actualizado."]);

        return back();
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
            'password' => 'contraseña',
        ];
    }
}
