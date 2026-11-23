<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * CRUD de super administradores del panel central.
 */

/** Inicia sesión con la contraseña ya confirmada (las rutas la piden). */
function asSuperAdmin(User $user): User
{
    test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    return $user;
}

function fakeSession(User $user): void
{
    DB::table('sessions')->insert([
        'id' => 'sesion-'.$user->id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'prueba',
        'payload' => '',
        'last_activity' => time(),
    ]);
}

test('managing super admins asks to confirm the password first', function () {
    $this->get(route('super-admins.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('super-admins.index'))
        ->assertRedirect(route('password.confirm'));
});

test('the list shows every super admin and who has two-step verification', function () {
    $me = asSuperAdmin(User::factory()->create(['name' => 'Ana']));
    User::factory()->withTwoFactor()->create(['name' => 'Beto']);

    $this->get(route('super-admins.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('super-admins/index')
            ->has('admins', 2)
            ->where('admins.0.name', 'Ana')
            ->where('admins.0.is_me', true)
            ->where('admins.0.two_factor', false)
            ->where('admins.1.two_factor', true)
            ->missing('admins.0.password'));

    expect($me)->not->toBeNull();
});

test('a super admin adds another one who can log in right away', function () {
    asSuperAdmin(User::factory()->create());

    $this->post(route('super-admins.store'), [
        'name' => 'Carla Gómez',
        'email' => '  Carla@Empresa.COM ',
        'password' => 'Clave-Segura-2026',
    ])->assertRedirect(route('super-admins.index'))->assertSessionHasNoErrors();

    $carla = User::where('email', 'carla@empresa.com')->sole();

    expect($carla->name)->toBe('Carla Gómez')
        ->and($carla->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Clave-Segura-2026', $carla->password))->toBeTrue();

    $this->post(route('super-admins.store'), ['name' => 'Otra', 'email' => 'CARLA@empresa.com', 'password' => 'Clave-Segura-2026'])
        ->assertSessionHasErrors(['email' => 'Ya hay un super administrador con este correo.']);

    $this->post(route('super-admins.store'), ['name' => 'Sin clave', 'email' => 'nueva@empresa.com'])
        ->assertSessionHasErrors('password');
});

test('changing another super admin password closes their open sessions', function () {
    config(['session.driver' => 'database']);
    asSuperAdmin(User::factory()->create());
    $other = User::factory()->create(['email' => 'otro@empresa.com']);
    fakeSession($other);

    $this->put(route('super-admins.update', $other), ['name' => 'Otro Renombrado', 'email' => 'otro@empresa.com'])
        ->assertSessionHasNoErrors();

    // Sin contraseña nueva, sus sesiones siguen.
    expect($other->fresh()->name)->toBe('Otro Renombrado')
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeTrue();

    // Tenía verificación en dos pasos y una llave de acceso (quizá puesta por un intruso).
    $other->forceFill(['two_factor_secret' => 'secreto', 'two_factor_confirmed_at' => now()])->save();
    DB::table('passkeys')->insert([
        'user_id' => $other->id, 'name' => 'Llave', 'credential_id' => 'cred-1', 'credential' => '{}',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->put(route('super-admins.update', $other), ['name' => 'Otro Renombrado', 'email' => 'otro@empresa.com', 'password' => 'Nueva-Clave-2026'])
        ->assertSessionHasNoErrors();

    // Recuperar la cuenta deja solo la contraseña nueva.
    expect(Hash::check('Nueva-Clave-2026', $other->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeFalse()
        ->and($other->fresh()->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('passkeys')->where('user_id', $other->id)->exists())->toBeFalse();
});

test('changing your own password closes your sessions on other devices', function () {
    config(['session.driver' => 'database']);
    $me = asSuperAdmin(User::factory()->create());
    fakeSession($me);

    $this->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'Nueva-Clave-2026',
        'password_confirmation' => 'Nueva-Clave-2026',
    ])->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', 'sesion-'.$me->id)->exists())->toBeFalse();
});

test('your own password is not changed from this page', function () {
    $me = asSuperAdmin(User::factory()->create());

    $this->put(route('super-admins.update', $me), ['name' => 'Yo', 'email' => $me->email, 'password' => 'Nueva-Clave-2026'])
        ->assertSessionHasNoErrors();

    expect($me->fresh()->name)->toBe('Yo')
        ->and(Hash::check('password', $me->fresh()->password))->toBeTrue();
});

test('removing a super admin ends their access, but nobody removes themselves', function () {
    config(['session.driver' => 'database']);
    $me = asSuperAdmin(User::factory()->create());
    $other = User::factory()->create();
    fakeSession($other);

    $this->delete(route('super-admins.destroy', $me))->assertRedirect(route('super-admins.index'));
    expect($me->fresh())->not->toBeNull();

    $this->delete(route('super-admins.destroy', $other))->assertRedirect(route('super-admins.index'));

    expect($other->fresh())->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeFalse();

    $this->delete(route('super-admins.destroy', $other))->assertNotFound();
    $this->put('/super-administradores/abc', [])->assertNotFound();
});
