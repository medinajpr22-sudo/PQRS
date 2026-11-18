<?php

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Models\Pqrs;
use App\Models\PqrsAttachment;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PqrsAssignedNotification;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Services\TenantProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;

/**
 * Crea una empresa de prueba con su propia base de datos.
 *
 * @param  array<string, mixed>  $formSchema
 */
function makeCompany(string $id, array $formSchema = []): Tenant
{
    return app(TenantProvisioner::class)->create([
        'id' => $id,
        'name' => "Empresa {$id}",
        'radicado_prefix' => strtoupper(substr($id, 0, 3)),
        'domain' => Tenant::subdomainToDomain($id),
        'form_schema' => $formSchema,
        'admin_name' => 'Admin',
        'admin_email' => "admin@{$id}.test",
        'admin_password' => 'password',
    ]);
}

/**
 * Crea un funcionario dentro de la empresa actual (llamar dentro de $tenant->run()).
 */
function makeStaff(string $name, UserRole $role = UserRole::Funcionario): User
{
    $user = new User(['name' => ucfirst($name), 'email' => "{$name}@staff.test", 'password' => 'password']);
    $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();

    return $user->fresh();
}

function portal(Tenant $tenant, string $path = ''): string
{
    return 'http://'.$tenant->primaryDomain().'/'.ltrim($path, '/');
}

/**
 * @return array<string, mixed>
 */
function validSubmission(array $overrides = []): array
{
    return array_merge([
        'type' => 'peticion',
        'requester_name' => 'Ciudadano de Prueba',
        'requester_document_type' => 'CC',
        'requester_document' => '123456789',
        'requester_email' => 'Ciudadano@Example.com',
        'subject' => 'Solicitud de información',
        'description' => 'Necesito información detallada sobre el estado de mi trámite.',
        'privacy' => '1',
    ], $overrides);
}

afterEach(function () {
    tenancy()->end();

    // Borra las bases de datos de prueba de cada empresa.
    Tenant::all()->each->delete();
});

test('super admin can create a company with its own database and admin user', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('tenants.store'), [
            'name' => 'Empresa Nueva S.A.S.',
            'plan' => 'basico',
            'subdomain' => 'empresa-nueva',
            'radicado_prefix' => 'ENS',
            'admin_name' => 'Ana Admin',
            'admin_email' => 'ana@empresa-nueva.test',
            'admin_password' => 'password123',
        ])
        ->assertRedirect(route('tenants.edit', 'empresa-nueva'));

    $tenant = Tenant::findOrFail('empresa-nueva');

    expect($tenant->primaryDomain())->toBe('empresa-nueva.localhost')
        ->and($tenant->radicado_prefix)->toBe('ENS')
        ->and($tenant->run(fn () => User::where('email', 'ana@empresa-nueva.test')->exists()))->toBeTrue();

    // El usuario de la empresa no existe en la base central.
    expect(User::where('email', 'ana@empresa-nueva.test')->exists())->toBeFalse();
});

test('central admin routes are not reachable from a company domain', function () {
    $tenant = makeCompany('aislada');

    $this->get(portal($tenant, 'dashboard'))->assertNotFound();
    $this->get(portal($tenant, 'login'))->assertNotFound();
    $this->get('http://no-existe.localhost/')->assertNotFound();
});

test('each company renders its own form schema', function () {
    $a = makeCompany('alfa', ['fields' => [
        ['key' => 'numero_poliza', 'label' => 'Número de póliza', 'type' => 'text', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => []],
    ]]);
    $b = makeCompany('beta');

    $this->get(portal($a, 'radicar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/portal/create')
            ->where('schema.fields.0.key', 'numero_poliza'));

    $this->get(portal($b, 'radicar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('schema.fields', []));
});

test('custom required fields are validated per company', function () {
    $tenant = makeCompany('gamma', ['fields' => [
        ['key' => 'sede', 'label' => 'Sede', 'type' => 'select', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => ['Norte', 'Sur']],
    ]]);

    $this->post(portal($tenant, 'radicar'), validSubmission())
        ->assertSessionHasErrors('fields.sede');

    $this->post(portal($tenant, 'radicar'), validSubmission(['fields' => ['sede' => 'Oriente']]))
        ->assertSessionHasErrors('fields.sede');
});

test('a citizen can file a pqrs, gets a radicado and an email', function () {
    Notification::fake();
    $tenant = makeCompany('delta');

    $this->post(portal($tenant, 'radicar'), validSubmission())
        ->assertRedirect(portal($tenant, 'radicado'));

    $pqrs = $tenant->run(fn () => Pqrs::sole());

    expect($pqrs->radicado)->toBe('DEL-'.now()->year.'-00001')
        ->and($pqrs->status)->toBe(PqrsStatus::Radicada)
        ->and($pqrs->requester_email)->toBe('ciudadano@example.com')
        ->and($pqrs->due_at->isAfter(now()->addDays(14)))->toBeTrue();

    Notification::assertSentOnDemand(PqrsReceivedNotification::class);
});

test('companies never see each other pqrs', function () {
    $a = makeCompany('epsilon');
    $b = makeCompany('zeta');

    $this->post(portal($a, 'radicar'), validSubmission());
    $radicado = $a->run(fn () => Pqrs::sole()->radicado);

    expect($b->run(fn () => Pqrs::count()))->toBe(0);

    // Consultar el radicado de A desde el portal de B no lo encuentra.
    $this->post(portal($b, 'consultar'), ['radicado' => $radicado, 'email' => 'ciudadano@example.com'])
        ->assertSessionHasErrors('radicado');

    // En el portal de A sí, ignorando mayúsculas.
    $this->post(portal($a, 'consultar'), ['radicado' => strtolower($radicado), 'email' => 'CIUDADANO@example.com'])
        ->assertRedirect(portal($a, "consultar/{$radicado}"));
});

test('central admin credentials do not work on a company panel', function () {
    User::factory()->create(['email' => 'root@pqrs.test']);
    $tenant = makeCompany('eta');

    $this->post(portal($tenant, 'admin/login'), ['email' => 'root@pqrs.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('staff can answer a pqrs and the citizen is notified', function () {
    Notification::fake();
    $tenant = makeCompany('theta');

    $this->post(portal($tenant, 'radicar'), validSubmission());
    [$staff, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);

    $this->actingAs($staff)
        ->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
            'type' => 'response',
            'body' => 'Su solicitud fue atendida satisfactoriamente.',
        ])
        ->assertSessionHasNoErrors();

    $pqrs = $tenant->run(fn () => Pqrs::sole());

    expect($pqrs->status)->toBe(PqrsStatus::Respondida)
        ->and($pqrs->responded_at)->not->toBeNull();

    Notification::assertSentOnDemand(PqrsRespondedNotification::class);
});

test('a suspended company portal is unavailable', function () {
    $tenant = makeCompany('iota');
    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    $this->get(portal($tenant))->assertStatus(503);
});

test('citizens can download response attachments but never internal note attachments', function () {
    Notification::fake();
    $tenant = makeCompany('kappa');

    $this->post(portal($tenant, 'radicar'), validSubmission());
    [$staff, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);

    foreach (['note' => 'interno.pdf', 'response' => 'respuesta.pdf'] as $type => $file) {
        $this->actingAs($staff)->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
            'type' => $type,
            'body' => 'Mensaje con un archivo adjunto.',
            'attachments' => [UploadedFile::fake()->create($file, 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();
    }

    $ids = $tenant->run(fn () => PqrsAttachment::pluck('id', 'original_name'));

    $this->withSession(["tracking.{$radicado}" => true])
        ->get(portal($tenant, "consultar/{$radicado}/adjuntos/{$ids['interno.pdf']}"))
        ->assertNotFound();

    $this->withSession(["tracking.{$radicado}" => true])
        ->get(portal($tenant, "consultar/{$radicado}/adjuntos/{$ids['respuesta.pdf']}"))
        ->assertOk();
});

test('status changes follow the allowed transitions and are always recorded', function () {
    Notification::fake();
    $tenant = makeCompany('lambda');

    $this->post(portal($tenant, 'radicar'), validSubmission());
    [$staff, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);
    $status = portal($tenant, "admin/pqrs/{$radicado}/estado");
    $activities = portal($tenant, "admin/pqrs/{$radicado}/actividades");

    // Una nota interna la pasa a "En trámite" y queda en el historial.
    $this->actingAs($staff)->post($activities, ['type' => 'note', 'body' => 'Revisando el caso.']);
    expect($tenant->run(fn () => Pqrs::sole()->status))->toBe(PqrsStatus::EnTramite);

    // "Respondida" solo se alcanza enviando una respuesta.
    $this->actingAs($staff)->patch($status, ['status' => 'respondida'])->assertSessionHasErrors('status');

    // Cerrar sin respuesta exige motivo.
    $this->actingAs($staff)->patch($status, ['status' => 'cerrada'])->assertSessionHasErrors('reason');
    $this->actingAs($staff)->patch($status, ['status' => 'cerrada', 'reason' => 'Solicitud duplicada.'])->assertSessionHasNoErrors();

    // Una solicitud cerrada no se responde sin reabrirla.
    $this->actingAs($staff)->post($activities, ['type' => 'response', 'body' => 'Respuesta tardía.'])->assertSessionHasErrors('body');

    // No puede volver a "Radicada"; reabrir exige motivo.
    $this->actingAs($staff)->patch($status, ['status' => 'radicada'])->assertSessionHasErrors('status');
    $this->actingAs($staff)->patch($status, ['status' => 'en_tramite'])->assertSessionHasErrors('reason');
    $this->actingAs($staff)->patch($status, ['status' => 'en_tramite', 'reason' => 'El ciudadano aportó información nueva.'])->assertSessionHasNoErrors();

    $pqrs = $tenant->run(fn () => Pqrs::sole()->load('activities'));

    expect($pqrs->status)->toBe(PqrsStatus::EnTramite)
        ->and($pqrs->closed_at)->toBeNull()
        ->and($pqrs->activities->where('type', 'status_changed')->count())->toBe(3);
});

test('radicado numbers are compared as numbers, not text', function () {
    Notification::fake();
    $tenant = makeCompany('mu');
    $year = now()->year;

    $tenant->run(fn () => Pqrs::create([
        'radicado' => "MU-{$year}-99999",
        'type' => 'peticion',
        'status' => PqrsStatus::Radicada,
        'requester_name' => 'Importada',
        'requester_email' => 'importada@example.com',
        'subject' => 'Solicitud importada',
        'description' => 'Solicitud que venía de otro sistema.',
    ]));

    $this->post(portal($tenant, 'radicar'), validSubmission());
    $this->post(portal($tenant, 'radicar'), validSubmission());

    expect($tenant->run(fn () => Pqrs::orderBy('id')->pluck('radicado')->all()))
        ->toBe(["MU-{$year}-99999", "MU-{$year}-100000", "MU-{$year}-100001"]);
});

test('admins assign pqrs to anyone and staff can only take unassigned ones', function () {
    Notification::fake();
    $tenant = makeCompany('nu');

    $this->post(portal($tenant, 'radicar'), validSubmission());
    [$admin, $ana, $luis, $radicado] = $tenant->run(fn () => [
        User::first(), makeStaff('ana'), makeStaff('luis'), Pqrs::sole()->radicado,
    ]);
    $url = portal($tenant, "admin/pqrs/{$radicado}/asignacion");

    $this->actingAs($admin)->patch($url, ['assigned_to' => $ana->id])->assertSessionHasNoErrors();
    Notification::assertSentTo($ana, PqrsAssignedNotification::class);

    // Un funcionario no puede quitarle la solicitud a otro.
    $this->actingAs($luis)->patch($url, ['assigned_to' => $luis->id])->assertSessionHasErrors('assigned_to');

    // Sí puede soltar la suya, y otro puede tomarla.
    $this->actingAs($ana)->patch($url, ['assigned_to' => null])->assertSessionHasNoErrors();
    $this->actingAs($luis)->patch($url, ['assigned_to' => $luis->id])->assertSessionHasNoErrors();

    $this->actingAs($luis)->get(portal($tenant, 'admin/pqrs?assigned=me'))
        ->assertInertia(fn ($page) => $page->where('pqrs.total', 1));

    expect($tenant->run(fn () => Pqrs::sole()->assigned_to))->toBe($luis->id);
});

test('only company admins manage users and deactivated staff cannot log in', function () {
    $tenant = makeCompany('xi');
    [$admin, $pedro] = $tenant->run(fn () => [User::first(), makeStaff('pedro')]);

    expect($admin->isAdmin())->toBeTrue();

    $this->actingAs($pedro)->get(portal($tenant, 'admin/usuarios'))->assertForbidden();

    $this->actingAs($admin)->post(portal($tenant, 'admin/usuarios'), [
        'name' => 'Nueva Funcionaria',
        'email' => 'nueva@staff.test',
        'role' => 'funcionario',
        'password' => 'password123',
    ])->assertSessionHasNoErrors();

    // El único administrador no puede quitarse su propio rol.
    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$admin->id}"), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'funcionario',
        'is_active' => true,
    ])->assertSessionHasErrors('role');

    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$pedro->id}"), [
        'name' => $pedro->name,
        'email' => $pedro->email,
        'role' => 'funcionario',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    $this->app['auth']->forgetGuards();

    $this->post(portal($tenant, 'admin/login'), ['email' => 'pedro@staff.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect($tenant->run(fn () => User::where('email', 'nueva@staff.test')->first()?->role))->toBe(UserRole::Funcionario);
});
