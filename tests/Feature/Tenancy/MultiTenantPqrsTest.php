<?php

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Models\Pqrs;
use App\Models\PqrsAttachment;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PqrsAssignedNotification;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Services\TenantProvisioner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
 * Crea un usuario de la empresa actual (llamar dentro de $tenant->run()).
 * Por defecto es un agente; $sedes son las sedes que atiende.
 *
 * @param  list<Sede>  $sedes
 */
function makeStaff(string $name, UserRole $role = UserRole::Agente, array $sedes = []): User
{
    $user = new User(['name' => ucfirst($name), 'email' => "{$name}@staff.test", 'password' => 'password']);
    $user->forceFill(['role' => $role, 'is_active' => true, 'email_verified_at' => now()])->save();
    $user->sedes()->sync(array_map(fn (Sede $s) => $s->id, $sedes));

    return $user->fresh();
}

/**
 * Crea una sede en la empresa actual (llamar dentro de $tenant->run()).
 */
function makeSede(string $name, bool $active = true): Sede
{
    return Sede::create(['name' => $name, 'is_active' => $active]);
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
            'database' => 'pqrs_empresa_nueva',
            'admin_name' => 'Ana Admin',
            'admin_email' => 'ana@empresa-nueva.test',
            'admin_password' => 'password123',
        ])
        ->assertRedirect(route('tenants.edit', 'empresa-nueva'));

    $tenant = Tenant::findOrFail('empresa-nueva');

    expect($tenant->primaryDomain())->toBe('empresa-nueva.localhost')
        ->and($tenant->radicado_prefix)->toBe('ENS')
        ->and($tenant->databaseName())->toBe('pqrs_empresa_nueva')
        ->and(file_exists(database_path('pqrs_empresa_nueva')))->toBeTrue()
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

    $sede = $tenant->run(fn () => makeSede('Neiva'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $sede->id]));
    [$admin, $ana, $luis, $radicado] = $tenant->run(fn () => [
        User::first(), makeStaff('ana', sedes: [$sede]), makeStaff('luis', sedes: [$sede]), Pqrs::sole()->radicado,
    ]);
    $url = portal($tenant, "admin/pqrs/{$radicado}/asignacion");

    $this->actingAs($admin)->patch($url, ['assigned_to' => $ana->id])->assertSessionHasNoErrors();
    Notification::assertSentTo($ana, PqrsAssignedNotification::class);

    // Un agente no puede quitarle la solicitud a otro.
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
        'name' => 'Nueva Agente',
        'email' => 'nueva@staff.test',
        'role' => 'agente',
        'password' => 'password123',
    ])->assertSessionHasNoErrors();

    // El único administrador no puede quitarse su propio rol.
    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$admin->id}"), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'agente',
        'is_active' => true,
    ])->assertSessionHasErrors('role');

    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$pedro->id}"), [
        'name' => $pedro->name,
        'email' => $pedro->email,
        'role' => 'agente',
        'is_active' => false,
    ])->assertSessionHasNoErrors();

    $this->app['auth']->forgetGuards();

    $this->post(portal($tenant, 'admin/login'), ['email' => 'pedro@staff.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    expect($tenant->run(fn () => User::where('email', 'nueva@staff.test')->first()?->role))->toBe(UserRole::Agente);
});

test('staff emails are case-insensitive for uniqueness and login', function () {
    $tenant = makeCompany('xc');
    [$admin] = $tenant->run(fn () => [User::first(), makeStaff('ana')]);

    // Ya existe ana@staff.test: con otras mayúsculas es un error de validación, no un 500.
    $this->actingAs($admin)->post(portal($tenant, 'admin/usuarios'), [
        'name' => 'Ana Duplicada',
        'email' => 'Ana@Staff.TEST',
        'role' => 'agente',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    $this->actingAs($admin)->post(portal($tenant, 'admin/usuarios'), [
        'name' => 'Luis Pérez',
        'email' => ' Luis.Perez@Staff.TEST ',
        'role' => 'agente',
        'password' => 'password123',
    ])->assertSessionHasNoErrors();

    expect($tenant->run(fn () => User::where('email', 'luis.perez@staff.test')->exists()))->toBeTrue();

    $this->app['auth']->forgetGuards();

    // Entra escribiendo el correo tal como se lo dieron, con mayúsculas.
    $this->post(portal($tenant, 'admin/login'), ['email' => 'Luis.Perez@Staff.test', 'password' => 'password123'])
        ->assertRedirect(route('tenant.admin.dashboard'));
});

test('changing a staff password closes that user sessions', function () {
    $tenant = makeCompany('xp');
    [$admin, $pedro] = $tenant->run(fn () => [User::first(), makeStaff('pedro')]);

    $tenant->run(fn () => DB::table('sessions')->insert([
        'id' => 'sesion-de-pedro',
        'user_id' => $pedro->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]));

    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$pedro->id}"), [
        'name' => $pedro->name,
        'email' => $pedro->email,
        'role' => 'agente',
        'is_active' => true,
        'password' => 'NuevaClave123',
    ])->assertSessionHasNoErrors();

    expect($tenant->run(fn () => DB::table('sessions')->where('user_id', $pedro->id)->exists()))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Sedes y roles: Super Admin, Admin de Empresa y Agente
|--------------------------------------------------------------------------
*/

test('the super admin database name must be valid and unused', function () {
    $superAdmin = User::factory()->create();
    makeCompany('omicron');

    $payload = fn (array $overrides) => [
        'name' => 'Otra Empresa',
        'plan' => 'basico',
        'subdomain' => 'otra-empresa',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@otra.test',
        'admin_password' => 'password123',
        ...$overrides,
    ];

    // Formato inválido, nombre reservado y una base que ya usa otra empresa.
    foreach (['Mi-Base', '1base', 'database', 'tenant_omicron'] as $name) {
        $this->actingAs($superAdmin)
            ->post(route('tenants.store'), $payload(['database' => $name]))
            ->assertSessionHasErrors('database');
    }

    expect(Tenant::find('otra-empresa'))->toBeNull();
});

test('citizens must choose an active sede when the company has sedes', function () {
    Notification::fake();
    $tenant = makeCompany('pi');

    // Sin sedes, el formulario no la pide.
    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();

    [$neiva, $cerrada] = $tenant->run(fn () => [makeSede('Neiva'), makeSede('Antigua', active: false)]);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasErrors('sede_id');
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $cerrada->id]))->assertSessionHasErrors('sede_id');
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]))->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::latest('id')->first()->sede_id))->toBe($neiva->id);
});

test('agents only see and manage pqrs from their own sedes', function () {
    Notification::fake();
    $tenant = makeCompany('rho');

    [$neiva, $yopal] = $tenant->run(fn () => [makeSede('Neiva'), makeSede('Yopal')]);
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id, 'subject' => 'Caso de Neiva']));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $yopal->id, 'subject' => 'Caso de Yopal']));

    [$admin, $agente, $deNeiva, $deYopal] = $tenant->run(fn () => [
        User::first(),
        makeStaff('carla', sedes: [$neiva]),
        Pqrs::where('sede_id', $neiva->id)->sole()->radicado,
        Pqrs::where('sede_id', $yopal->id)->sole()->radicado,
    ]);

    // Bandeja y resumen: solo su sede. El administrador ve todo.
    $this->actingAs($agente)->get(portal($tenant, 'admin/pqrs'))
        ->assertInertia(fn ($page) => $page->where('pqrs.total', 1)->where('pqrs.data.0.radicado', $deNeiva));
    $this->actingAs($agente)->get(portal($tenant, 'admin'))
        ->assertInertia(fn ($page) => $page->where('stats.total', 1)->has('bySede', 1));
    $this->actingAs($admin)->get(portal($tenant, 'admin/pqrs'))
        ->assertInertia(fn ($page) => $page->where('pqrs.total', 2));
    $this->actingAs($admin)->get(portal($tenant, 'admin'))
        ->assertInertia(fn ($page) => $page->where('stats.total', 2)->has('bySede', 2));

    // Ver, responder y anotar en su sede.
    $this->actingAs($agente)->get(portal($tenant, "admin/pqrs/{$deNeiva}"))->assertOk();
    $this->actingAs($agente)->post(portal($tenant, "admin/pqrs/{$deNeiva}/actividades"), [
        'type' => 'note', 'body' => 'Revisando con la sede.',
    ])->assertSessionHasNoErrors();
    $this->actingAs($agente)->post(portal($tenant, "admin/pqrs/{$deNeiva}/actividades"), [
        'type' => 'response', 'body' => 'Su caso fue resuelto por la sede Neiva.',
    ])->assertSessionHasNoErrors();

    // La de otra sede «no existe» para él: ni verla ni tocarla.
    $this->actingAs($agente)->get(portal($tenant, "admin/pqrs/{$deYopal}"))->assertNotFound();
    $this->actingAs($agente)->post(portal($tenant, "admin/pqrs/{$deYopal}/actividades"), [
        'type' => 'note', 'body' => 'No debería poder.',
    ])->assertNotFound();
    $this->actingAs($agente)->patch(portal($tenant, "admin/pqrs/{$deYopal}/estado"), ['status' => 'en_tramite'])->assertNotFound();
    $this->actingAs($agente)->patch(portal($tenant, "admin/pqrs/{$deYopal}/asignacion"), ['assigned_to' => $agente->id])->assertNotFound();
    $this->actingAs($agente)->patch(portal($tenant, "admin/pqrs/{$deYopal}/sede"), ['sede_id' => $neiva->id])->assertNotFound();

    // Y no administra sedes ni usuarios.
    $this->actingAs($agente)->get(portal($tenant, 'admin/sedes'))->assertForbidden();
    $this->actingAs($agente)->post(portal($tenant, 'admin/sedes'), ['name' => 'Pitalito'])->assertForbidden();

    expect($tenant->run(fn () => Pqrs::where('radicado', $deYopal)->sole()->activities()->count()))->toBe(1);
});

test('an agent can move a pqrs to another sede and loses access to it', function () {
    Notification::fake();
    $tenant = makeCompany('sigma');

    [$neiva, $yopal] = $tenant->run(fn () => [makeSede('Neiva'), makeSede('Yopal')]);
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    [$agente, $radicado] = $tenant->run(fn () => [makeStaff('carla', sedes: [$neiva]), Pqrs::sole()->radicado]);

    // La toma y luego la traslada a Yopal, que no atiende.
    $this->actingAs($agente)->patch(portal($tenant, "admin/pqrs/{$radicado}/asignacion"), ['assigned_to' => $agente->id]);
    $this->actingAs($agente)
        ->patch(portal($tenant, "admin/pqrs/{$radicado}/sede"), ['sede_id' => $yopal->id, 'reason' => 'El paciente se atiende en Yopal.'])
        ->assertRedirect(portal($tenant, 'admin/pqrs'));

    $pqrs = $tenant->run(fn () => Pqrs::sole()->load('activities'));

    // Queda en Yopal, sin responsable (Carla no atiende Yopal) y con el traslado en el historial interno.
    expect($pqrs->sede_id)->toBe($yopal->id)
        ->and($pqrs->assigned_to)->toBeNull()
        ->and($pqrs->activities->firstWhere('type', 'sede_changed')?->is_public)->toBeFalse();

    $this->actingAs($agente)->get(portal($tenant, "admin/pqrs/{$radicado}"))->assertNotFound();
});

test('the company admin assigns sedes and removing one releases the agent pqrs there', function () {
    Notification::fake();
    $tenant = makeCompany('tau');

    [$neiva, $yopal] = $tenant->run(fn () => [makeSede('Neiva'), makeSede('Yopal')]);
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $yopal->id]));

    [$admin, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);

    // Crea un agente con la sede Yopal.
    $this->actingAs($admin)->post(portal($tenant, 'admin/usuarios'), [
        'name' => 'Mario Agente',
        'email' => 'mario@staff.test',
        'role' => 'agente',
        'sedes' => [$yopal->id],
        'password' => 'password123',
    ])->assertSessionHasNoErrors();

    $mario = $tenant->run(fn () => User::where('email', 'mario@staff.test')->sole());

    expect($tenant->run(fn () => $mario->sedes()->pluck('sedes.id')->all()))->toBe([$yopal->id]);

    // Un agente sin la sede no puede ser responsable; con la sede, sí.
    $otro = $tenant->run(fn () => makeStaff('nora', sedes: [$neiva]));
    $assign = portal($tenant, "admin/pqrs/{$radicado}/asignacion");
    $this->actingAs($admin)->patch($assign, ['assigned_to' => $otro->id])->assertSessionHasErrors('assigned_to');
    $this->actingAs($admin)->patch($assign, ['assigned_to' => $mario->id])->assertSessionHasNoErrors();

    // Le cambia Yopal por Neiva: la PQRS de Yopal queda sin responsable.
    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$mario->id}"), [
        'name' => $mario->name,
        'email' => $mario->email,
        'role' => 'agente',
        'is_active' => true,
        'sedes' => [$neiva->id],
    ])->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::sole()->assigned_to))->toBeNull();

    $this->actingAs($admin)->get(portal($tenant, 'admin/sedes'))
        ->assertInertia(fn ($page) => $page->component('tenant/admin/sedes/index')->has('sedes', 2));
});

test('pqrs without a sede are only visible to the company admin', function () {
    Notification::fake();
    $tenant = makeCompany('upsilon');

    // Radicada antes de que la empresa tuviera sedes.
    $this->post(portal($tenant, 'radicar'), validSubmission());
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    [$admin, $agente, $radicado] = $tenant->run(fn () => [User::first(), makeStaff('carla', sedes: [$neiva]), Pqrs::sole()->radicado]);

    $this->actingAs($agente)->get(portal($tenant, "admin/pqrs/{$radicado}"))->assertNotFound();

    // El administrador le asigna la sede y desde ahí el agente la ve.
    $this->actingAs($admin)->patch(portal($tenant, "admin/pqrs/{$radicado}/sede"), ['sede_id' => $neiva->id])->assertSessionHasNoErrors();
    $this->actingAs($agente)->get(portal($tenant, "admin/pqrs/{$radicado}"))->assertOk();
});

test('the super admin edits the name and email of a company admin', function () {
    $superAdmin = User::factory()->create();
    $tenant = makeCompany('phi');
    [$admin, $otro, $agente] = $tenant->run(fn () => [
        User::first(),
        makeStaff('otro', UserRole::Admin),
        makeStaff('carla'),
    ]);

    $this->actingAs($superAdmin)->get(route('tenants.edit', $tenant))
        ->assertInertia(fn ($page) => $page->has('tenant.admins', 2));

    $url = fn (User $u) => route('tenants.admins.update', ['tenant' => $tenant, 'admin' => $u->id]);

    // Un correo que ya usa otra persona de la empresa es un error de validación.
    $this->actingAs($superAdmin)
        ->put($url($admin), ['name' => 'Admin', 'email' => 'OTRO@staff.test'])
        ->assertSessionHasErrors('email');

    // El error no deja la petición «dentro» de la empresa.
    expect(tenancy()->initialized)->toBeFalse();

    $this->actingAs($superAdmin)
        ->put($url($admin), ['name' => 'Laura Directora', 'email' => ' Laura@Phi.TEST '])
        ->assertSessionHasNoErrors();

    expect($tenant->run(fn () => User::find($admin->id)->only(['name', 'email'])))
        ->toBe(['name' => 'Laura Directora', 'email' => 'laura@phi.test']);

    // Solo administradores: un agente no se edita desde aquí.
    $this->actingAs($superAdmin)
        ->put($url($agente), ['name' => 'X', 'email' => 'x@phi.test'])
        ->assertNotFound();

    // Ya entra al panel de la empresa con el correo nuevo.
    $this->app['auth']->forgetGuards();
    $this->post(portal($tenant, 'admin/login'), ['email' => 'laura@phi.test', 'password' => 'password'])
        ->assertRedirect(route('tenant.admin.dashboard'));
});

test('company admins cannot use the super admin endpoint', function () {
    $tenant = makeCompany('chi');
    $admin = $tenant->run(fn () => User::first());

    // Las rutas centrales no existen en el dominio de la empresa.
    $this->actingAs($admin)
        ->put('http://'.$tenant->primaryDomain()."/empresas/chi/administradores/{$admin->id}", ['name' => 'X', 'email' => 'x@chi.test'])
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Plantilla «Salud» y reclamos por nivel de riesgo (Supersalud)
|--------------------------------------------------------------------------
*/

test('the super admin can choose the salud portal template', function () {
    $superAdmin = User::factory()->create();
    $tenant = makeCompany('psi');

    $this->actingAs($superAdmin)->post(route('tenants.theme.update', $tenant), [
        'layout' => 'salud',
        'primary_color' => '#f26b1d',
        'accent_color' => '#000000',
        'font' => 'circe-rounded',
        'hero_title' => 'PQRS-F',
    ])->assertSessionHasNoErrors();

    expect(Tenant::find('psi')->resolvedTheme()['layout'])->toBe('salud');
});

test('reclamos ask for a risk level only when the company enables it', function () {
    Notification::fake();

    // Sin la opción, el reclamo no pide nivel de riesgo.
    $sin = makeCompany('omega');
    $this->post(portal($sin, 'radicar'), validSubmission(['type' => 'reclamo']))->assertSessionHasNoErrors();
    expect($sin->run(fn () => Pqrs::sole()->risk_level))->toBeNull();

    // Con la opción: obligatorio en reclamos, válido y se guarda.
    $con = makeCompany('alfa', ['risk_levels' => true]);
    $this->get(portal($con, '/'))->assertInertia(fn ($page) => $page->has('riskLevels', 3));
    $this->get(portal($con, 'radicar?tipo=reclamo&riesgo=vital'))
        ->assertInertia(fn ($page) => $page->has('riskLevels', 3)->where('initialRisk', 'vital'));

    $this->post(portal($con, 'radicar'), validSubmission(['type' => 'reclamo']))->assertSessionHasErrors('risk_level');
    $this->post(portal($con, 'radicar'), validSubmission(['type' => 'reclamo', 'risk_level' => 'grave']))->assertSessionHasErrors('risk_level');
    $this->post(portal($con, 'radicar'), validSubmission(['type' => 'reclamo', 'risk_level' => 'simple', 'subject' => 'Reclamo simple']));
    $this->post(portal($con, 'radicar'), validSubmission(['type' => 'reclamo', 'risk_level' => 'vital', 'subject' => 'Reclamo vital']));

    // En otros tipos no se pide y no se guarda.
    $this->post(portal($con, 'radicar'), validSubmission(['type' => 'peticion', 'risk_level' => 'vital']))->assertSessionHasNoErrors();

    expect($con->run(fn () => Pqrs::orderBy('id')->get()->map(fn (Pqrs $p) => $p->risk_level?->value)->all()))
        ->toBe(['simple', 'vital', null]);

    // El resumen muestra primero el riesgo vital.
    $admin = $con->run(fn () => User::first());
    $this->actingAs($admin)->get(portal($con, 'admin'))
        ->assertInertia(fn ($page) => $page->where('urgent.0.risk_level', 'vital'));
});

test('the tracking form arrives with the radicado from the home bar', function () {
    $tenant = makeCompany('beta');

    $this->get(portal($tenant, 'consultar?radicado=bet-2026-00001'))
        ->assertInertia(fn ($page) => $page->component('tenant/portal/track')->where('radicado', 'BET-2026-00001'));
});

test('each company can have its own admin panel colors', function () {
    $superAdmin = User::factory()->create();
    $tenant = makeCompany('kappa');
    $theme = [
        'layout' => 'corporate',
        'primary_color' => '#1d4ed8',
        'accent_color' => '#f59e0b',
        'font' => 'circe-rounded',
        'hero_title' => 'PQRS',
    ];

    // Por defecto, el vino y rojo de la plataforma.
    expect($tenant->resolvedTheme())->toMatchArray(['panel_color' => '#5b1a24', 'panel_accent' => '#c62833']);

    $this->actingAs($superAdmin)
        ->post(route('tenants.theme.update', $tenant), [...$theme, 'panel_color' => 'negro', 'panel_accent' => '#f26b1d'])
        ->assertSessionHasErrors('panel_color');

    $this->actingAs($superAdmin)
        ->post(route('tenants.theme.update', $tenant), [...$theme, 'panel_color' => '#111111', 'panel_accent' => '#f26b1d'])
        ->assertSessionHasNoErrors();

    expect(Tenant::find('kappa')->resolvedTheme())->toMatchArray(['panel_color' => '#111111', 'panel_accent' => '#f26b1d']);

    // En el panel central, editar una empresa no lo convierte en panel de empresa.
    $this->actingAs($superAdmin)->get(route('tenants.edit', $tenant))
        ->assertInertia(fn ($page) => $page->where('context', 'central'));

    // El panel de la empresa recibe sus colores y sabe que está «dentro» de una empresa.
    $admin = $tenant->run(fn () => User::first());
    $this->actingAs($admin)->get(portal($tenant, 'admin'))
        ->assertInertia(fn ($page) => $page
            ->where('context', 'tenant')
            ->where('tenant.theme.panel_color', '#111111')
            ->where('myOpenPqrs', 0));
});

test('logging in records the last access shown in the users page', function () {
    $tenant = makeCompany('lambda2');
    $tenant->run(fn () => makeStaff('rosa'));

    $this->post(portal($tenant, 'admin/login'), ['email' => 'rosa@staff.test', 'password' => 'password'])
        ->assertRedirect(route('tenant.admin.dashboard'));

    expect($tenant->run(fn () => User::where('email', 'rosa@staff.test')->value('last_login_at')))->not->toBeNull();
});
