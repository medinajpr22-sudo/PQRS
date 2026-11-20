<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/profile');
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Siempre queda al menos un super administrador. Bloquea las cuentas
        // para que dos personas no se eliminen a la vez y dejen el panel solo.
        $deleted = DB::transaction(function () use ($user): bool {
            // PostgreSQL no deja bloquear y contar en la misma consulta: se
            // bloquean las filas y después se cuentan.
            $admins = User::query()->lockForUpdate()->pluck('id');

            if ($admins->count() <= 1) {
                return false;
            }

            // Primero se cierra la sesión: al cerrarla, Laravel guarda el
            // usuario (cambia su «recordarme») y lo volvería a crear.
            Auth::logout();

            return (bool) $user->delete();
        });

        if (! $deleted) {
            throw ValidationException::withMessages([
                'password' => 'Eres el único super administrador: agrega otro antes de eliminar tu cuenta.',
            ]);
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
