<?php

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Tenant\PqrsTrackingController;
use App\Mail\MicrosoftGraphTransport;
use App\Models\Pqrs;
use App\Models\PqrsAttachment;
use App\Models\PqrsDeletion;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewPqrsForSedeNotification;
use App\Notifications\PqrsAssignedNotification;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Rules\EmailDomainReceivesMail;
use App\Services\TenantProvisioner;
use App\Support\MailBranding;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\Mime\Email;

// La administración de empresas y el perfil piden confirmar la contraseña
// (RequirePassword): estas pruebas parten con la contraseña ya confirmada.
beforeEach(function () {
    $this->withSession(['auth.password_confirmed_at' => time()]);
});

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
        'requester_is_affected' => '1',
        'subject' => 'Solicitud de información',
        'description' => 'Necesito información detallada sobre el estado de mi trámite.',
        'privacy' => '1',
    ], $overrides);
}

afterEach(function () {
    tenancy()->end();
    EmailDomainReceivesMail::resolveUsing(null);

    // Borra las bases de datos de prueba de cada empresa y sus archivos
    // (adjuntos y logos), para no dejar basura en storage/.
    Tenant::all()->each(function (Tenant $tenant) {
        File::deleteDirectory(storage_path(config('tenancy.filesystem.suffix_base').$tenant->id));
        File::deleteDirectory(storage_path("app/public/tenants/{$tenant->id}"));
        $tenant->delete();
    });
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
        // Vale para SQLite (archivo) y PostgreSQL (CREATE DATABASE).
        ->and($tenant->database()->manager()->databaseExists('pqrs_empresa_nueva'))->toBeTrue()
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

    // Solo el responsable responde: primero hay que aceptar serlo.
    $this->actingAs($staff)
        ->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
            'type' => 'response',
            'body' => 'Su solicitud fue atendida satisfactoriamente.',
        ])
        ->assertSessionHasErrors('body');

    $this->actingAs($staff)
        ->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
            'type' => 'response',
            'body' => 'Su solicitud fue atendida satisfactoriamente.',
            'take' => '1',
        ])
        ->assertSessionHasNoErrors();

    $pqrs = $tenant->run(fn () => Pqrs::sole());

    expect($pqrs->status)->toBe(PqrsStatus::Respondida)
        ->and($pqrs->responded_at)->not->toBeNull()
        ->and($pqrs->assigned_to)->toBe($staff->id);

    Notification::assertSentOnDemand(PqrsRespondedNotification::class);
});

test('a suspended company portal is unavailable', function () {
    $tenant = makeCompany('iota');
    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    $this->get(portal($tenant))->assertStatus(503);
});

test('suspending a company twice does not reactivate it', function () {
    $tenant = makeCompany('doble');
    $url = route('tenants.status.update', $tenant);
    $this->actingAs(User::factory()->create());

    // Doble clic: dos «suspender» seguidos la dejan suspendida.
    $this->patch($url, ['status' => 'suspended'])->assertSessionHasNoErrors();
    $this->patch($url, ['status' => 'suspended'])->assertSessionHasNoErrors();
    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_SUSPENDED);

    $this->patch($url, ['status' => 'active'])->assertSessionHasNoErrors();
    expect($tenant->fresh()->isActive())->toBeTrue();
});

test('citizens can download response attachments but never internal note attachments', function () {
    Notification::fake();
    $tenant = makeCompany('kappa');

    $this->post(portal($tenant, 'radicar'), validSubmission());
    [$staff, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);

    foreach (['note' => 'interno.pdf', 'response' => 'respuesta.pdf'] as $type => $file) {
        $this->actingAs($staff)->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
            'type' => $type,
            'take' => '1',
            'body' => 'Mensaje con un archivo adjunto.',
            'attachments' => [UploadedFile::fake()->create($file, 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();
    }

    $ids = $tenant->run(fn () => PqrsAttachment::pluck('id', 'original_name'));

    $this->withSession(["tracking.{$radicado}" => now()->getTimestamp()])
        ->get(portal($tenant, "consultar/{$radicado}/adjuntos/{$ids['interno.pdf']}"))
        ->assertNotFound();

    $this->withSession(["tracking.{$radicado}" => now()->getTimestamp()])
        ->get(portal($tenant, "consultar/{$radicado}/adjuntos/{$ids['respuesta.pdf']}"))
        ->assertOk();

    // El correo lleva la respuesta y solo su archivo, nunca el de la nota interna.
    Notification::assertSentOnDemand(PqrsRespondedNotification::class, function ($n, $channels, $notifiable) use ($tenant, $radicado) {
        $mail = $tenant->run(fn () => $n->toMail($notifiable));

        return count($mail->attachments) === 1
            && $mail->attachments[0]['options']['as'] === 'respuesta.pdf'
            && $mail->viewData['quote'] === 'Mensaje con un archivo adjunto.'
            && str_ends_with($mail->actionUrl, "consultar?radicado={$radicado}");
    });
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
    $this->actingAs($staff)->post($activities, ['type' => 'response', 'take' => '1', 'body' => 'Respuesta tardía.'])->assertSessionHasErrors('body');

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
        'type' => 'response', 'take' => '1', 'body' => 'Su caso fue resuelto por la sede Neiva.',
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

test('an agent cannot take a colleague pqrs by moving it to another sede', function () {
    Notification::fake();
    $tenant = makeCompany('traslado');

    [$neiva, $yopal] = $tenant->run(fn () => [makeSede('Neiva'), makeSede('Yopal')]);
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    [$ana, $luis, $radicado] = $tenant->run(fn () => [
        makeStaff('ana', sedes: [$neiva]),
        makeStaff('luis', sedes: [$neiva, $yopal]),
        Pqrs::sole()->radicado,
    ]);

    $this->actingAs($ana)->patch(portal($tenant, "admin/pqrs/{$radicado}/asignacion"), ['assigned_to' => $ana->id]);

    // Trasladarla a Yopal (donde Ana no atiende) la dejaría libre para tomarla.
    $this->actingAs($luis)
        ->patch(portal($tenant, "admin/pqrs/{$radicado}/sede"), ['sede_id' => $yopal->id])
        ->assertSessionHasErrors('sede_id');

    expect($tenant->run(fn () => Pqrs::sole()->only(['sede_id', 'assigned_to'])))
        ->toBe(['sede_id' => $neiva->id, 'assigned_to' => $ana->id]);
});

test('a note from someone who is not in charge does not change the status', function () {
    Notification::fake();
    $tenant = makeCompany('notaajena');

    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    [$ana, $luis, $radicado] = $tenant->run(fn () => [makeStaff('ana', sedes: [$neiva]), makeStaff('luis', sedes: [$neiva]), Pqrs::sole()->radicado]);

    $this->actingAs($ana)->patch(portal($tenant, "admin/pqrs/{$radicado}/asignacion"), ['assigned_to' => $ana->id]);
    $this->actingAs($luis)
        ->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), ['type' => 'note', 'body' => 'Llamé al paciente.'])
        ->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::sole()->status))->toBe(PqrsStatus::Radicada);

    // La de su responsable sí la pone «En trámite».
    $this->actingAs($ana)->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), ['type' => 'note', 'body' => 'Revisando la agenda.']);

    expect($tenant->run(fn () => Pqrs::sole()->status))->toBe(PqrsStatus::EnTramite);
});

test('a colleague can take over a pqrs whose assignee was deactivated', function () {
    Notification::fake();
    $tenant = makeCompany('relevo');

    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    [$ana, $luis, $radicado] = $tenant->run(fn () => [makeStaff('ana', sedes: [$neiva]), makeStaff('luis', sedes: [$neiva]), Pqrs::sole()->radicado]);

    // Ana responde (queda «Respondida» a su cargo) y luego la desactivan.
    $this->actingAs($ana)
        ->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), ['type' => 'response', 'take' => '1', 'body' => 'Su cita quedó programada.'])
        ->assertSessionHasNoErrors();
    $tenant->run(fn () => User::whereKey($ana->id)->update(['is_active' => false]));

    // Luis ya no queda bloqueado: puede cerrarla o tomarla.
    $this->actingAs($luis)
        ->patch(portal($tenant, "admin/pqrs/{$radicado}/estado"), ['status' => 'cerrada'])
        ->assertSessionHasNoErrors();
    $this->actingAs($luis)
        ->patch(portal($tenant, "admin/pqrs/{$radicado}/asignacion"), ['assigned_to' => $luis->id])
        ->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::sole()->only(['status', 'assigned_to'])))
        ->toBe(['status' => PqrsStatus::Cerrada, 'assigned_to' => $luis->id]);
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

/**
 * Activa el «No soy un robot» con un reto fácil para que las pruebas sean rápidas.
 */
function enableCaptcha(): void
{
    config(['pqrs.captcha' => [
        'enabled' => true,
        'cost' => 1,
        'counter' => ['min' => 1, 'max' => 20],
        'expires_minutes' => 20,
    ]]);
}

/**
 * Pide un reto al portal y lo resuelve como lo haría el navegador.
 */
function solvedCaptcha(Tenant $tenant): string
{
    $json = test()->getJson(portal($tenant, 'radicar/verificacion'))->assertOk()->json();
    $challenge = Challenge::fromArray($json);
    $solution = (new Altcha)->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: new Pbkdf2));

    return (new Payload($challenge, $solution))->toBase64();
}

test('the pqrs form requires the not-a-robot check and each check works once', function () {
    enableCaptcha();
    $tenant = makeCompany('robot');

    $this->get(portal($tenant, 'radicar'))
        ->assertInertia(fn ($page) => $page->where('captcha', true));

    // Sin marcar la casilla, o con una solución inventada, no se radica nada.
    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasErrors('altcha');
    $this->post(portal($tenant, 'radicar'), validSubmission(['altcha' => base64_encode('{"challenge":{},"solution":{}}')]))
        ->assertSessionHasErrors('altcha');

    // Una solución con la clave alterada tampoco sirve.
    $forged = json_decode(base64_decode(solvedCaptcha($tenant)), true);
    $forged['solution']['derivedKey'] = str_repeat('0', 64);
    $this->post(portal($tenant, 'radicar'), validSubmission(['altcha' => base64_encode(json_encode($forged))]))
        ->assertSessionHasErrors('altcha');

    expect($tenant->run(fn () => Pqrs::count()))->toBe(0);

    // Con la casilla marcada se radica, pero la misma verificación no se puede repetir.
    $captcha = solvedCaptcha($tenant);
    $this->post(portal($tenant, 'radicar'), validSubmission(['altcha' => $captcha]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(portal($tenant, 'radicado'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['altcha' => $captcha]))
        ->assertSessionHasErrors('altcha');

    expect($tenant->run(fn () => Pqrs::count()))->toBe(1);
});

test('a not-a-robot check only works for the company that issued it and expires', function () {
    enableCaptcha();
    $a = makeCompany('robota');
    $b = makeCompany('robotb');

    $this->post(portal($b, 'radicar'), validSubmission(['altcha' => solvedCaptcha($a)]))
        ->assertSessionHasErrors('altcha');

    $captcha = solvedCaptcha($b);
    $this->travel(21)->minutes();
    $this->post(portal($b, 'radicar'), validSubmission(['altcha' => $captcha]))
        ->assertSessionHasErrors('altcha');

    expect($b->run(fn () => Pqrs::count()))->toBe(0);
});

test('fixing other fields does not spend the not-a-robot check', function () {
    enableCaptcha();
    $tenant = makeCompany('robotc');
    $captcha = solvedCaptcha($tenant);

    $this->post(portal($tenant, 'radicar'), validSubmission(['subject' => '', 'altcha' => $captcha]))
        ->assertSessionHasErrors('subject')
        ->assertSessionDoesntHaveErrors('altcha');

    $this->post(portal($tenant, 'radicar'), validSubmission(['altcha' => $captcha]))
        ->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::count()))->toBe(1);
});

test('without the not-a-robot check enabled the form works as before', function () {
    $tenant = makeCompany('robotd');

    $this->getJson(portal($tenant, 'radicar/verificacion'))->assertNotFound();
    $this->get(portal($tenant, 'radicar'))
        ->assertInertia(fn ($page) => $page->where('captcha', false));
    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();
});

test('only the person in charge answers and agents cannot take a pqrs from someone else', function () {
    Notification::fake();
    $tenant = makeCompany('cargo');
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    [$admin, $juan, $ana] = $tenant->run(fn () => [
        User::first(),
        makeStaff('juan', UserRole::Agente, [$neiva]),
        makeStaff('ana', UserRole::Agente, [$neiva]),
    ]);

    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);
    $reply = portal($tenant, "admin/pqrs/{$radicado}/actividades");

    // Juan asume la solicitud al responder.
    $this->actingAs($juan)->post($reply, ['type' => 'note', 'body' => 'Las notas no piden ser responsable.'])->assertSessionHasNoErrors();
    $this->actingAs($juan)->post($reply, ['type' => 'response', 'take' => '1', 'body' => 'Primera respuesta al ciudadano.'])->assertSessionHasNoErrors();
    expect($tenant->run(fn () => Pqrs::sole()->assigned_to))->toBe($juan->id);

    // Ana no puede quitársela, ni siquiera aceptando ser responsable.
    $this->actingAs($ana)->post($reply, ['type' => 'response', 'take' => '1', 'body' => 'Intento responder la de Juan.'])->assertSessionHasErrors('body');
    expect($tenant->run(fn () => Pqrs::sole()->assigned_to))->toBe($juan->id);

    // El administrador sí puede asumirla para responder.
    $this->actingAs($admin)->post($reply, ['type' => 'response', 'take' => '1', 'body' => 'Complemento de la respuesta.'])->assertSessionHasNoErrors();
    expect($tenant->run(fn () => Pqrs::sole()->assigned_to))->toBe($admin->id);
});

test('the company admin changes its own colors and agents cannot', function () {
    $tenant = makeCompany('pintura');
    [$admin, $agente] = $tenant->run(fn () => [User::first(), makeStaff('pedro')]);
    $colors = ['primary_color' => '#15803D', 'accent_color' => '#e11d48', 'panel_color' => '#064e3b', 'panel_accent' => '#1d4ed8'];

    $this->actingAs($agente)->get(portal($tenant, 'admin/colores'))->assertForbidden();
    $this->actingAs($agente)->put(portal($tenant, 'admin/colores'), $colors)->assertForbidden();

    $this->actingAs($admin)->get(portal($tenant, 'admin/colores'))
        ->assertInertia(fn ($page) => $page->component('tenant/admin/colors')->has('colors.panel_color'));
    $this->actingAs($admin)->put(portal($tenant, 'admin/colores'), [...$colors, 'panel_color' => 'verde'])
        ->assertSessionHasErrors('panel_color');
    $this->actingAs($admin)->put(portal($tenant, 'admin/colores'), $colors)->assertSessionHasNoErrors();

    $theme = Tenant::find('pintura')->resolvedTheme();

    expect($theme)->toMatchArray(['primary_color' => '#15803d', 'panel_color' => '#064e3b', 'panel_accent' => '#1d4ed8'])
        ->and($theme['layout'])->toBe('corporate');
});

test('new pqrs are emailed to the agents of their sede', function () {
    Notification::fake();
    $tenant = makeCompany('avisos');
    [$admin, $juan, $ana, $luis, $inactivo, $neiva, $yopal] = $tenant->run(function () {
        $neiva = makeSede('Neiva');
        $yopal = makeSede('Yopal');
        $inactivo = makeStaff('inactivo', UserRole::Agente, [$neiva]);
        $inactivo->forceFill(['is_active' => false])->save();

        return [
            User::first(),
            makeStaff('juan', UserRole::Agente, [$neiva, $yopal]),
            makeStaff('ana', UserRole::Agente, [$neiva]),
            makeStaff('luis', UserRole::Agente, [$yopal]),
            $inactivo, $neiva, $yopal,
        ];
    });

    // A Neiva: le llega a Juan (Neiva y Yopal) y a Ana, no a Luis ni al inactivo.
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));
    Notification::assertSentTo([$juan, $ana], NewPqrsForSedeNotification::class);
    Notification::assertNotSentTo([$luis, $inactivo, $admin], NewPqrsForSedeNotification::class);

    // A Yopal: a Juan y a Luis.
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $yopal->id]));
    Notification::assertSentToTimes($juan, NewPqrsForSedeNotification::class, 2);
    Notification::assertSentTo($luis, NewPqrsForSedeNotification::class);
    Notification::assertSentToTimes($ana, NewPqrsForSedeNotification::class, 1);

    // Si Ana la traslada a Yopal, avisa a los de Yopal pero no a ella.
    $radicado = $tenant->run(fn () => Pqrs::where('sede_id', $neiva->id)->value('radicado'));
    $this->actingAs($ana)->patch(portal($tenant, "admin/pqrs/{$radicado}/sede"), ['sede_id' => $yopal->id])
        ->assertSessionHasNoErrors();
    Notification::assertSentToTimes($luis, NewPqrsForSedeNotification::class, 2);
    Notification::assertSentToTimes($ana, NewPqrsForSedeNotification::class, 1);

    // Una sede sin agentes avisa al administrador.
    $vacia = $tenant->run(fn () => makeSede('Garzón'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $vacia->id]));
    Notification::assertSentTo($admin, NewPqrsForSedeNotification::class);
});

test('companies without sedes email new pqrs to their admins', function () {
    Notification::fake();
    $tenant = makeCompany('sinsedes');
    $admin = $tenant->run(fn () => User::first());

    $this->post(portal($tenant, 'radicar'), validSubmission());

    Notification::assertSentTo($admin, NewPqrsForSedeNotification::class, function ($n, $channels, $notifiable) {
        $mail = $n->toMail($notifiable);

        return str_contains($mail->subject, 'Nueva PQRS') && $mail->actionUrl !== null;
    });
});

test('health companies record the patient, service and attention date', function () {
    Notification::fake();
    $tenant = makeCompany('renal', ['services' => ['Hemodiálisis', 'Nefropediatría']]);

    $this->get(portal($tenant, 'radicar'))->assertInertia(fn ($page) => $page
        ->where('schema.health_data', true)
        ->where('schema.services', ['Hemodiálisis', 'Nefropediatría'])
        ->has('patientDocumentTypes')
        ->has('healthRegimes', 4));

    // Si lo radica otra persona, los datos del paciente son obligatorios.
    // (Pocos envíos: el portal permite 5 por minuto.)
    $this->post(portal($tenant, 'radicar'), validSubmission([
        'requester_is_affected' => '0',
        'service' => 'Cirugía',
        'attended_on' => now()->addDay()->toDateString(),
    ]))->assertSessionHasErrors(['affected_name', 'affected_document', 'service', 'attended_on']);
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_is_affected' => null]))
        ->assertSessionHasErrors('requester_is_affected');

    $this->post(portal($tenant, 'radicar'), validSubmission([
        'requester_is_affected' => '0',
        'requester_relationship' => 'Madre',
        'affected_name' => 'Sofía Pérez',
        'affected_document_type' => 'RC',
        'affected_document' => '1.105.123.456',
        'affected_email' => 'Sofia@Example.com',
        'eps' => 'Nueva EPS',
        'health_regime' => 'subsidiado',
        'service' => 'Nefropediatría',
        'attended_on' => '2026-11-10',
    ]))->assertSessionHasNoErrors();

    // Si el solicitante es el afectado, no se guardan datos de otra persona.
    $this->post(portal($tenant, 'radicar'), validSubmission([
        'affected_name' => 'No debería guardarse',
        'service' => 'Hemodiálisis',
    ]))->assertSessionHasNoErrors();

    [$forOther, $forSelf] = $tenant->run(fn () => Pqrs::orderBy('id')->get()->all());

    expect($forOther->requester_is_affected)->toBeFalse()
        ->and($forOther->requester_relationship)->toBe('Madre')
        ->and($forOther->affected_name)->toBe('Sofía Pérez')
        ->and($forOther->affected_document)->toBe('1105123456')
        ->and($forOther->affected_email)->toBe('sofia@example.com')
        ->and($forOther->health_regime)->toBe('subsidiado')
        ->and($forOther->service)->toBe('Nefropediatría')
        ->and($forOther->attended_on->toDateString())->toBe('2026-11-10')
        ->and($forSelf->requester_is_affected)->toBeTrue()
        ->and($forSelf->affected_name)->toBeNull()
        ->and($forSelf->service)->toBe('Hemodiálisis');

    // El equipo ve al paciente en el detalle.
    $admin = $tenant->run(fn () => User::first());
    $this->actingAs($admin)->get(portal($tenant, 'admin/pqrs/'.$forOther->radicado))
        ->assertInertia(fn ($page) => $page
            ->where('pqrs.health.affected_name', 'Sofía Pérez')
            ->where('pqrs.health.affected_document', 'Registro civil 1105123456')
            ->where('pqrs.health.health_regime', 'Subsidiado')
            ->where('pqrs.health.attended_on', '2026-11-10'));
});

test('companies outside health do not ask for patient data', function () {
    Notification::fake();
    $tenant = makeCompany('tienda', ['health_data' => false]);

    $data = validSubmission();
    unset($data['requester_is_affected']);

    $this->post(portal($tenant, 'radicar'), [...$data, 'eps' => 'Nueva EPS'])->assertSessionHasNoErrors();

    $pqrs = $tenant->run(fn () => Pqrs::sole());
    expect($pqrs->requester_is_affected)->toBeNull()->and($pqrs->eps)->toBeNull();
});

test('document numbers must match their type', function () {
    Notification::fake();
    $tenant = makeCompany('docs');

    // Así llegaba el spam: «CC gyh5ty».
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_document' => 'gyh5ty']))
        ->assertSessionHasErrors(['requester_document' => 'El número de documento solo puede tener números, sin letras.']);
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_document_type' => 'PA', 'requester_document' => 'AB-12']))
        ->assertSessionHasErrors('requester_document');

    // Puntos y espacios se quitan; el NIT admite el dígito de verificación.
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_document' => '1.075 234.567']))->assertSessionHasNoErrors();
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_document_type' => 'NIT', 'requester_document' => '900.123.456-7']))->assertSessionHasNoErrors();
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_document_type' => 'PA', 'requester_document' => 'ab123456']))->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::orderBy('id')->pluck('requester_document')->all()))
        ->toBe(['1075234567', '900123456-7', 'AB123456']);
});

test('the hidden trap field stops form robots', function () {
    Notification::fake();
    $tenant = makeCompany('trampa');

    $this->post(portal($tenant, 'radicar'), validSubmission(['website' => 'http://spam.example']))
        ->assertSessionHasErrors('altcha');

    expect($tenant->run(fn () => Pqrs::count()))->toBe(0);
});

test('the super admin configures the health section and services', function () {
    $tenant = makeCompany('config');
    $schema = $tenant->resolvedFormSchema();

    $this->actingAs(User::factory()->create())
        ->put(route('tenants.form.update', $tenant), [
            ...$schema,
            'health_data' => false,
            'services' => ['Hemodiálisis', 'hemodiálisis'],
        ])
        ->assertSessionHasErrors('services.1');

    $this->put(route('tenants.form.update', $tenant), [
        ...$schema,
        'health_data' => false,
        'services' => ['Hemodiálisis', 'Consulta externa'],
    ])->assertSessionHasNoErrors();

    $saved = $tenant->fresh()->resolvedFormSchema();
    expect($saved['health_data'])->toBeFalse()
        ->and($saved['services'])->toBe(['Hemodiálisis', 'Consulta externa']);
});

test('companies can have a browser icon separate from their logo', function () {
    Storage::fake('public');
    $superAdmin = User::factory()->create();
    $tenant = makeCompany('icono');
    // PNG real de 1×1 px (las pruebas no tienen GD para generar imágenes).
    $png = fn (string $name) => UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='));
    // Se arma antes: tras visitar el portal, route() usaría el dominio de la empresa.
    $update = route('tenants.theme.update', $tenant);
    $base = ['layout' => 'corporate', 'primary_color' => '#1d4ed8', 'accent_color' => '#f59e0b', 'font' => 'circe-rounded', 'hero_title' => 'PQRS'];

    // Sin ícono, la pestaña usa el logo.
    $this->actingAs($superAdmin)
        ->post($update, [...$base, 'logo' => $png('logo.png')])
        ->assertSessionHasNoErrors();
    $logo = Tenant::find('icono')->resolvedTheme()['logo_url'];
    $this->get(portal($tenant))->assertSee('<link rel="icon" href="'.$logo.'">', false);

    // Con ícono, la pestaña usa el ícono y el logo no cambia.
    $this->post($update, [...$base, 'icon' => $png('icono.png')])
        ->assertSessionHasNoErrors();
    $theme = Tenant::find('icono')->resolvedTheme();
    expect($theme['logo_url'])->toBe($logo)->and($theme['icon_url'])->not->toBeNull();
    Storage::disk('public')->assertExists($theme['icon_path']);
    tenancy()->end(); // Que el portal vuelva a leer la empresa con su ícono nuevo.
    $this->get(portal($tenant))
        ->assertSee('<link rel="icon" href="'.$theme['icon_url'].'">', false)
        ->assertSee('<link rel="apple-touch-icon" href="'.$theme['icon_url'].'">', false);

    // SVG no: se serviría desde nuestro dominio y podría traer scripts.
    $this->post($update, [...$base, 'icon' => UploadedFile::fake()->create('icono.svg', 2, 'image/svg+xml')])
        ->assertSessionHasErrors('icon');

    // Quitar el ícono borra el archivo y la pestaña vuelve al logo.
    $this->post($update, [...$base, 'remove_icon' => '1'])->assertSessionHasNoErrors();
    Storage::disk('public')->assertMissing($theme['icon_path']);
    expect(Tenant::find('icono')->resolvedTheme())
        ->icon_url->toBeNull()
        ->logo_url->toBe($logo);
});

/*
|--------------------------------------------------------------------------
| Revisión de lógica: responsables, concurrencia, consulta y correos
|--------------------------------------------------------------------------
*/

test('answering a pqrs that was just closed does not change who is in charge', function () {
    Notification::fake();
    $tenant = makeCompany('atomica');
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    [$admin, $juan] = $tenant->run(fn () => [User::first(), makeStaff('juan', UserRole::Agente, [$neiva])]);

    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);
    $reply = portal($tenant, "admin/pqrs/{$radicado}/actividades");

    $this->actingAs($juan)->post($reply, ['type' => 'response', 'take' => '1', 'body' => 'Respuesta de Juan al ciudadano.'])->assertSessionHasNoErrors();
    $this->actingAs($juan)->patch(portal($tenant, "admin/pqrs/{$radicado}/estado"), ['status' => 'cerrada'])->assertSessionHasNoErrors();

    // El administrador responde desde una pantalla vieja: ya estaba cerrada.
    $this->actingAs($admin)->post($reply, ['type' => 'response', 'take' => '1', 'body' => 'Respuesta que ya no aplica.'])
        ->assertSessionHasErrors('body');

    $pqrs = $tenant->run(fn () => Pqrs::sole());
    expect($pqrs->assigned_to)->toBe($juan->id)
        ->and($pqrs->status)->toBe(PqrsStatus::Cerrada)
        ->and($tenant->run(fn () => $pqrs->activities()->where('type', 'response')->count()))->toBe(1);
});

test('only the person in charge or an admin changes the status', function () {
    Notification::fake();
    $tenant = makeCompany('estados');
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    [$admin, $juan, $ana] = $tenant->run(fn () => [
        User::first(),
        makeStaff('juan', UserRole::Agente, [$neiva]),
        makeStaff('ana', UserRole::Agente, [$neiva]),
    ]);

    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);
    $status = portal($tenant, "admin/pqrs/{$radicado}/estado");

    // Sin responsable, cualquier agente de la sede puede moverla.
    $this->actingAs($ana)->patch($status, ['status' => 'en_tramite'])->assertSessionHasNoErrors();

    $tenant->run(fn () => Pqrs::sole()->forceFill(['assigned_to' => $juan->id])->save());

    // Con responsable, Ana ya no puede cerrarla; el administrador sí.
    $this->actingAs($ana)->get(portal($tenant, "admin/pqrs/{$radicado}"))
        ->assertInertia(fn ($page) => $page->where('canChangeStatus', false));
    $this->actingAs($ana)->patch($status, ['status' => 'cerrada', 'reason' => 'Lo cierro yo.'])->assertSessionHasErrors('status');
    expect($tenant->run(fn () => Pqrs::sole()->status))->toBe(PqrsStatus::EnTramite);

    $this->actingAs($admin)->patch($status, ['status' => 'cerrada', 'reason' => 'Solicitud duplicada.'])->assertSessionHasNoErrors();
    expect($tenant->run(fn () => Pqrs::sole()->status))->toBe(PqrsStatus::Cerrada);
});

test('deactivating an agent frees their open pqrs for the rest of the sede', function () {
    Notification::fake();
    $tenant = makeCompany('relevo');
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    [$admin, $juan] = $tenant->run(fn () => [User::first(), makeStaff('juan', UserRole::Agente, [$neiva])]);

    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    $tenant->run(function () use ($juan) {
        Pqrs::query()->update(['assigned_to' => $juan->id]);
        Pqrs::orderBy('id')->first()->forceFill(['status' => PqrsStatus::Respondida, 'responded_at' => now()])->save();
    });

    $this->actingAs($admin)->put(portal($tenant, "admin/usuarios/{$juan->id}"), [
        'name' => 'Juan', 'email' => 'juan@staff.test', 'role' => 'agente', 'is_active' => false, 'sedes' => [$neiva->id],
    ])->assertSessionHasNoErrors();

    [$responded, $open] = $tenant->run(fn () => Pqrs::orderBy('id')->get()->all());
    expect($open->assigned_to)->toBeNull()
        ->and($responded->assigned_to)->toBe($juan->id);

    // Si la respondida se reabre, también queda libre.
    $this->actingAs($admin)->patch(portal($tenant, "admin/pqrs/{$responded->radicado}/estado"), [
        'status' => 'en_tramite', 'reason' => 'El ciudadano pidió ampliar la respuesta.',
    ])->assertSessionHasNoErrors();

    expect($tenant->run(fn () => $responded->fresh()->assigned_to))->toBeNull();
});

test('public tracking limits guesses and does not reveal which radicados exist', function () {
    Notification::fake();
    $tenant = makeCompany('rastreo');
    $this->post(portal($tenant, 'radicar'), validSubmission());
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);

    // Uno ajeno (existe, sin haberlo consultado) y uno que no existe: igual.
    $this->get(portal($tenant, "consultar/{$radicado}"))->assertRedirect(portal($tenant, 'consultar'));
    $this->get(portal($tenant, 'consultar/RAS-2026-99999'))->assertRedirect(portal($tenant, 'consultar'));
    $this->get(portal($tenant, 'consultar/RAS-2026-99999/adjuntos/1'))->assertRedirect(portal($tenant, 'consultar'));

    // Probar radicados con el correo de otra persona se bloquea.
    foreach (range(1, 8) as $i) {
        $this->post(portal($tenant, 'consultar'), ['radicado' => "RAS-2026-0000{$i}9", 'email' => 'ciudadano@example.com'])
            ->assertSessionHasErrors('radicado');
    }

    $this->post(portal($tenant, 'consultar'), ['radicado' => $radicado, 'email' => 'ciudadano@example.com'])
        ->assertSessionHasErrors('radicado');
    expect(session('errors')->first('radicado'))->toContain('Demasiados intentos');
});

test('tracking access expires and can be closed on a shared computer', function () {
    Notification::fake();
    $tenant = makeCompany('kiosco');
    $this->post(portal($tenant, 'radicar'), validSubmission());
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);
    $open = fn () => $this->post(portal($tenant, 'consultar'), ['radicado' => $radicado, 'email' => 'ciudadano@example.com']);

    $open()->assertRedirect(portal($tenant, "consultar/{$radicado}"));
    $this->get(portal($tenant, "consultar/{$radicado}"))->assertOk();

    // «Cerrar consulta»: quien llegue después no puede volver atrás y verla.
    $this->post(portal($tenant, "consultar/{$radicado}/cerrar"))->assertRedirect(portal($tenant, 'consultar'));
    $this->get(portal($tenant, "consultar/{$radicado}"))->assertRedirect(portal($tenant, 'consultar'));

    // Y si nadie la cierra, vence sola.
    $open();
    $this->travel(PqrsTrackingController::ACCESS_MINUTES + 1)->minutes();
    $this->get(portal($tenant, "consultar/{$radicado}"))->assertRedirect(portal($tenant, 'consultar'));
});

test('failed guesses from one connection do not lock the radicado for its owner', function () {
    Notification::fake();
    $tenant = makeCompany('bloqueo');
    $this->post(portal($tenant, 'radicar'), validSubmission());
    $radicado = $tenant->run(fn () => Pqrs::sole()->radicado);

    foreach (range(1, 8) as $i) {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->post(portal($tenant, 'consultar'), ['radicado' => $radicado, 'email' => "intruso{$i}@example.com"]);
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->post(portal($tenant, 'consultar'), ['radicado' => $radicado, 'email' => 'ciudadano@example.com'])
        ->assertRedirect(portal($tenant, "consultar/{$radicado}"));
});

test('odd urls answer 404 or show the page instead of failing', function () {
    $tenant = makeCompany('urls');
    $admin = $tenant->run(fn () => User::first());

    $this->get(portal($tenant, 'consultar?radicado[]=x'))->assertOk();
    $this->get(portal($tenant, 'radicar?riesgo[]=x&tipo[]=y'))->assertOk();
    $this->actingAs($admin)->get(portal($tenant, 'admin/pqrs?status[]=x&search[]=y&type[]=z'))->assertOk();
    $this->actingAs($admin)->put(portal($tenant, 'admin/usuarios/abc'), [])->assertNotFound();
    $this->actingAs($admin)->put(portal($tenant, 'admin/sedes/abc'), [])->assertNotFound();
});

test('what the citizen types never becomes a link or image in the emails', function () {
    Notification::fake();
    $tenant = makeCompany('correo');

    $this->post(portal($tenant, 'radicar'), validSubmission([
        'requester_name' => 'Juan [Verifique su cuenta](https://evil.example/login)',
        'subject' => "Consulta\n\n![x](https://evil.example/p.png) [clic](https://evil.example)",
    ]))->assertSessionHasNoErrors();

    $html = null;
    Notification::assertSentOnDemand(PqrsReceivedNotification::class, function ($notification, $channels, $notifiable) use (&$html) {
        $html = (string) $notification->toMail($notifiable)->render();

        return true;
    });

    expect($html)->toContain('Verifique su cuenta')
        ->not->toContain('href="https://evil.example')
        ->not->toContain('src="https://evil.example')
        ->and($tenant->run(fn () => Pqrs::sole()->subject))->not->toContain("\n");
});

/**
 * Le pone a la empresa un logo de $bytes bytes (PNG de 1×1 px relleno) en el
 * disco público central, donde lo deja el super administrador.
 */
function giveLogo(Tenant $tenant, string $file = 'logo.png', int $bytes = 0): void
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    $relative = "tenants/{$tenant->id}/{$file}";

    File::ensureDirectoryExists(storage_path("app/public/tenants/{$tenant->id}"));
    File::put(storage_path("app/public/{$relative}"), str_pad($png, max($bytes, strlen($png)), "\0"));

    $tenant->theme = [...($tenant->theme ?? []), 'logo_path' => $relative];
    $tenant->save();
}

/**
 * El correo que recibió $address (del transporte «array» de las pruebas).
 */
function sentEmailTo(string $address): Email
{
    $sent = collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($message) => $message->getOriginalMessage())
        ->first(fn (Email $email) => strcasecmp($email->getTo()[0]->getAddress(), $address) === 0);

    expect($sent)->toBeInstanceOf(Email::class);

    return $sent;
}

test('company emails carry the logo inside the message', function () {
    $tenant = makeCompany('logo-correo');
    giveLogo($tenant);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();

    $email = sentEmailTo(validSubmission()['requester_email']);
    $logo = collect($email->getAttachments())->first(fn ($part) => $part->getName() === MailBranding::LOGO_CID);

    expect($email->getHtmlBody())->toContain('src="cid:'.MailBranding::LOGO_CID.'"')
        ->and($logo)->not->toBeNull()
        ->and($logo->getDisposition())->toBe('inline')
        ->and($logo->getMediaType().'/'.$logo->getMediaSubtype())->toBe('image/png');
});

test('citizen text in emails never turns into a link', function () {
    $tenant = makeCompany('enlaces');

    $this->post(portal($tenant, 'radicar'), validSubmission([
        'requester_name' => 'Juan. Su afiliación fue suspendida, actualice en eps-pagos.co',
    ]))->assertSessionHasNoErrors();

    $html = sentEmailTo(validSubmission()['requester_email'])->getHtmlBody();

    // Se lee igual, pero Gmail y Outlook no lo convierten en enlace.
    expect($html)->not->toContain('eps-pagos.co')
        ->toContain("eps-pagos.\u{200B}co");
});

test('queued staff emails are skipped for people who left meanwhile', function () {
    Notification::fake();
    $tenant = makeCompany('salieron');
    $neiva = $tenant->run(fn () => makeSede('Neiva'));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $neiva->id]));

    $tenant->run(function () use ($tenant, $neiva) {
        $pqrs = Pqrs::sole();
        $ana = makeStaff('ana', sedes: [$neiva]);
        $nuevo = new NewPqrsForSedeNotification($pqrs, $tenant);
        $asignada = new PqrsAssignedNotification($pqrs, $tenant, User::first());

        expect($nuevo->shouldSend($ana, 'mail'))->toBeTrue();

        // La desactivan (o le quitan la sede) mientras el correo esperaba en la cola.
        $ana->forceFill(['is_active' => false])->save();
        expect($nuevo->shouldSend($ana->fresh(), 'mail'))->toBeFalse();

        // El aviso de asignación solo sale si sigue siendo la responsable.
        $pqrs->forceFill(['assigned_to' => User::first()->id])->save();
        expect($asignada->shouldSend(User::first(), 'mail'))->toBeTrue()
            ->and($asignada->shouldSend($ana->fresh(), 'mail'))->toBeFalse();
    });
});

test('response attachments that would not fit next to the logo are sent as a download', function () {
    config(['pqrs.mail.max_attachments_kb' => 300]);
    $tenant = makeCompany('pesado');
    giveLogo($tenant, bytes: 200 * 1024);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();
    [$staff, $radicado] = $tenant->run(fn () => [User::first(), Pqrs::sole()->radicado]);

    // 150 KB caben en 300 KB, pero no junto a un logo de 200 KB.
    $this->actingAs($staff)->post(portal($tenant, "admin/pqrs/{$radicado}/actividades"), [
        'type' => 'response',
        'take' => '1',
        'body' => 'Adjuntamos el soporte de su cita.',
        'attachments' => [UploadedFile::fake()->create('soporte.pdf', 150, 'application/pdf')],
    ])->assertSessionHasNoErrors();

    $response = collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($message) => $message->getOriginalMessage())
        ->first(fn (Email $email) => str_starts_with((string) $email->getSubject(), 'Respuesta a tu solicitud'));

    expect(collect($response->getAttachments())->map(fn ($part) => $part->getFilename())->all())->not->toContain('soporte.pdf')
        ->and($response->getHtmlBody())->toContain('puedes descargarlos al consultar tu radicado');
});

test('attachments have a size limit per file and for the whole submission', function () {
    config(['pqrs.attachments.max_kb' => 100, 'pqrs.attachments.max_total_kb' => 150]);
    Notification::fake();
    $tenant = makeCompany('limites');
    $pdf = fn (string $name, int $kb) => UploadedFile::fake()->create($name, $kb, 'application/pdf');

    $this->post(portal($tenant, 'radicar'), validSubmission(['attachments' => [$pdf('grande.pdf', 120)]]))
        ->assertSessionHasErrors(['attachments.0' => 'Cada archivo puede pesar máximo 100 KB. Reduce su tamaño (por ejemplo, guardándolo como PDF comprimido) e inténtalo de nuevo.']);

    $this->post(portal($tenant, 'radicar'), validSubmission(['attachments' => [$pdf('uno.pdf', 90), $pdf('dos.pdf', 90)]]))
        ->assertSessionHasErrors(['attachments' => 'Los archivos suman 180 KB y el máximo por envío es 150 KB. Quita alguno o redúcelos de tamaño.']);

    $this->post(portal($tenant, 'radicar'), validSubmission(['attachments' => [$pdf('uno.pdf', 90)]]))
        ->assertSessionHasNoErrors();

    // El formulario muestra los mismos límites (nunca más de lo que acepta PHP).
    $this->get(portal($tenant, 'radicar'))->assertInertia(fn ($page) => $page
        ->where('attachmentRules.max_kb', 100)
        ->where('attachmentRules.max_total_kb', 150));
});

test('the mail test command can send a sample with a company design', function () {
    $tenant = makeCompany('muestra');
    giveLogo($tenant);

    $this->artisan('pqrs:probar-correo', ['correo' => 'yo@example.com', '--empresa' => 'muestra'])->assertSuccessful();

    $email = sentEmailTo('yo@example.com');

    expect($email->getFrom()[0]->getName())->toBe('Empresa muestra')
        ->and($email->getHtmlBody())->toContain('src="cid:'.MailBranding::LOGO_CID.'"')
        ->and($email->getHtmlBody())->toContain('MUE-'.now()->year.'-00000');

    $this->artisan('pqrs:probar-correo', ['correo' => 'yo@example.com', '--empresa' => 'no-existe'])
        ->expectsOutputToContain('No existe la empresa «no-existe»')
        ->assertExitCode(2);
});

test('emails show the company name when the logo cannot travel inside them', function (string $file, int $bytes) {
    $tenant = makeCompany('sin-logo');
    giveLogo($tenant, $file, $bytes);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();

    // En desarrollo el portal no es público: tampoco se enlaza.
    $email = sentEmailTo(validSubmission()['requester_email']);

    expect($email->getHtmlBody())->not->toContain('cid:')
        ->toContain('Empresa sin-logo')
        ->and($email->getAttachments())->toBeEmpty();
})->with([
    'webp (Outlook no lo muestra)' => ['logo.webp', 0],
    'demasiado pesado' => ['logo.png', 400 * 1024],
]);

test('the dashboard computes the on-time rate in the database', function () {
    $tenant = makeCompany('tasa');
    $admin = $tenant->run(fn () => User::first());

    $tenant->run(function () {
        $base = ['type' => 'peticion', 'status' => PqrsStatus::Respondida, 'requester_name' => 'X', 'requester_email' => 'x@example.com', 'subject' => 'S', 'description' => 'D'];
        // A tiempo: respondida 2 días después, vencía en 10.
        Pqrs::create([...$base, 'radicado' => 'TAS-1'])->forceFill([
            'created_at' => now()->subDays(10), 'responded_at' => now()->subDays(8), 'due_at' => now(),
        ])->save();
        // Tarde: respondida 4 días después, vencía en 1.
        Pqrs::create([...$base, 'radicado' => 'TAS-2'])->forceFill([
            'created_at' => now()->subDays(10), 'responded_at' => now()->subDays(6), 'due_at' => now()->subDays(9),
        ])->save();
    });

    $this->actingAs($admin)->get(portal($tenant, 'admin'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.on_time_rate', 50)
            ->where('stats.avg_response_days', 3));
});

test('deleting a company removes its database, files and pending emails', function () {
    $tenant = makeCompany('borrar');
    $database = $tenant->databaseName();
    $storage = storage_path(config('tenancy.filesystem.suffix_base').'borrar');
    File::ensureDirectoryExists("{$storage}/app/pqrs");
    File::put("{$storage}/app/pqrs/soporte.txt", 'x');

    $job = fn (string $id) => ['queue' => 'default', 'payload' => json_encode(['uuid' => $id, 'tenant_id' => $id]), 'attempts' => 0, 'available_at' => time(), 'created_at' => time()];
    DB::table('jobs')->insert([$job('borrar'), $job('otra')]);

    // En PostgreSQL, una conexión abierta a la base (como la de la cola de
    // correos) no impide borrarla.
    if (DB::getDriverName() === 'pgsql') {
        config(['database.connections.abierta' => ['database' => $database] + config('database.connections.pgsql')]);
        DB::connection('abierta')->select('SELECT 1');
    }

    $this->actingAs(User::factory()->create())
        ->delete(route('tenants.destroy', $tenant), ['confirmation' => 'borrar'])
        ->assertRedirect(route('tenants.index'));

    DB::purge('abierta');

    expect(Tenant::find('borrar'))->toBeNull()
        ->and($tenant->database()->manager()->databaseExists($database))->toBeFalse()
        ->and(File::exists($storage))->toBeFalse()
        ->and(DB::table('jobs')->pluck('payload')->all())->toBe([json_encode(['uuid' => 'otra', 'tenant_id' => 'otra'])]);
});

test('the seeder never creates the super admin with the known password in production', function () {
    app()->instance('env', 'production');
    app(DatabaseSeeder::class)->run();
    expect(User::where('email', 'admin@pqrs.test')->exists())->toBeFalse();

    config(['pqrs.super_admin.password' => 'Una-Clave-Segura-2026']);
    app(DatabaseSeeder::class)->run();
    expect(User::where('email', 'admin@pqrs.test')->exists())->toBeTrue();
});

test('emails go out at the mailbox pace, say they are automatic and replies go to the company', function () {
    Notification::fake();
    config(['pqrs.mail.per_minute' => 20, 'pqrs.mail.per_day' => 250]);
    $tenant = makeCompany('ritmo');
    $tenant->update(['email' => 'contacto@ritmo.test']);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(PqrsReceivedNotification::class, function ($notification, $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);
        $email = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($email);
        }

        expect($mail->from)->toBe([config('mail.from.address'), 'Empresa ritmo'])
            ->and($mail->replyTo)->toBe([['contacto@ritmo.test', 'Empresa ritmo']])
            ->and($email->getHeaders()->get('Auto-Submitted')?->getBodyAsString())->toBe('auto-generated')
            // Los rebotes deben seguir llegando: solo se callan las respuestas automáticas.
            ->and($email->getHeaders()->get('X-Auto-Response-Suppress')?->getBodyAsString())->toBe('OOF, AutoReply')
            ->and($notification->middleware($notifiable, 'mail')[0])->toBeInstanceOf(RateLimited::class)
            // Esperar turno no cuenta como fallo: se rinde después de días, no
            // de 5 intentos, y aguanta una cola detenida en un puente festivo.
            ->and($notification->retryUntil()->getTimestamp())->toBeGreaterThan(now()->addDays(4)->getTimestamp());

        return true;
    });

    $limits = collect(RateLimiter::limiter('outgoing-mail')(new stdClass));

    expect($limits->map(fn ($l) => [$l->maxAttempts, $l->decaySeconds])->all())->toBe([[20, 60], [250, 86400]])
        // Una sola clave para todas las empresas: el buzón que envía es uno.
        ->and($limits->pluck('key')->all())->toBe(['outgoing-mail-minute', 'outgoing-mail-day']);
});

test('a citizen address only gets a few acknowledgments a day', function () {
    Notification::fake();
    config(['pqrs.mail.acks_per_address' => 2]);
    $tenant = makeCompany('acuses');

    foreach (range(1, 3) as $i) {
        $this->post(portal($tenant, 'radicar'), validSubmission(['subject' => "Solicitud {$i}"]))
            ->assertRedirect(portal($tenant, 'radicado'));

        // El comprobante no promete un correo que no se envió.
        $this->get(portal($tenant, 'radicado'))
            ->assertInertia(fn ($page) => $page->where('receipt.emailed', $i <= 2));
    }

    expect($tenant->run(fn () => Pqrs::count()))->toBe(3);
    Notification::assertSentOnDemandTimes(PqrsReceivedNotification::class, 2);
});

test('citizen emails with a typo or a domain that does not receive mail are rejected', function () {
    Notification::fake();
    config(['pqrs.mail.check_dns' => true]);
    // DNS de mentiras: solo existen gmail.com y empresa.co.
    EmailDomainReceivesMail::resolveUsing(fn (string $domain) => in_array($domain, ['gmail.com', 'empresa.co'], true));
    $tenant = makeCompany('dominio');

    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_email' => 'ana@gmail.co']))
        ->assertSessionHasErrors(['requester_email' => 'Revisa el correo electrónico: el dominio «gmail.co» no existe o no recibe correos.']);

    // «gmial.com» lo tiene un tercero y podría recibir correo: se sugiere el correcto.
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_email' => 'Ana.Perez@Gmial.com']))
        ->assertSessionHasErrors(['requester_email' => 'Revisa el correo electrónico: ¿quisiste decir «ana.perez@gmail.com»?']);

    expect($tenant->run(fn () => Pqrs::count()))->toBe(0);

    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_email' => 'pqrs@empresa.co']))
        ->assertSessionHasNoErrors();

    // Si el DNS del servidor no responde, nadie se queda sin radicar.
    EmailDomainReceivesMail::resolveUsing(fn () => false);
    $this->post(portal($tenant, 'radicar'), validSubmission(['requester_email' => 'ana@otro-dominio.co']))
        ->assertSessionHasNoErrors();

    expect($tenant->run(fn () => Pqrs::count()))->toBe(2);
});

test('the mail test command explains why sending failed', function () {
    $this->artisan('pqrs:probar-correo', ['correo' => 'ana@example.com'])
        ->expectsOutputToContain('no salió')
        ->assertSuccessful();

    // Para no salir a internet, el envío va a un puerto local cerrado.
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);

    $this->artisan('pqrs:probar-correo', ['correo' => 'ana@example.com'])
        ->expectsOutputToContain('No hubo conexión con el servidor')
        ->assertFailed();
});

test('a filed pqrs reaches the citizen and the team through microsoft 365', function () {
    MicrosoftGraphTransport::forgetTokens();
    config([
        'mail.default' => 'microsoft-graph',
        'mail.from.address' => 'pqrs@empresa.test',
        'mail.mailers.microsoft-graph' => ['transport' => 'microsoft-graph', 'tenant_id' => 'org', 'client_id' => 'app', 'client_secret' => 'secreto'],
    ]);
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'permiso', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::response(null, 202),
    ]);
    $tenant = makeCompany('graph');
    $tenant->update(['email' => 'contacto@graph.test']);

    $this->post(portal($tenant, 'radicar'), validSubmission())->assertSessionHasNoErrors();

    $mimes = Http::recorded(fn ($request) => str_contains($request->url(), 'graph.microsoft.com'))
        ->map(fn (array $pair) => (string) base64_decode($pair[0]->body(), true));
    $citizen = $mimes->first(fn (string $mime) => str_contains($mime, 'To: ciudadano@example.com'));

    expect($citizen)->toBeString()
        ->toContain('From: Empresa graph <pqrs@empresa.test>')
        ->toContain('Reply-To: Empresa graph <contacto@graph.test>')
        ->toContain('Auto-Submitted: auto-generated')
        // La sede no tiene agentes: el aviso va a los administradores.
        ->and($mimes->contains(fn (string $mime) => str_contains($mime, 'To: admin@graph.test')))->toBeTrue();

    MicrosoftGraphTransport::forgetTokens();
});

/**
 * Lee un Excel descargado: sus filas (como texto) y el XML de la hoja.
 *
 * @return array{rows: list<list<string>>, xml: string}
 */
function readXlsx(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'pqrs').'.xlsx';
    file_put_contents($path, $content);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(fn ($v) => $v instanceof DateTimeInterface ? $v->format('Y-m-d H:i') : (string) $v, $row->toArray());
        }
    }

    $reader->close();
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    return ['rows' => $rows, 'xml' => $xml];
}

test('a company admin downloads the filtered pqrs list in excel', function () {
    Notification::fake();
    $tenant = makeCompany('excel');
    [$norte, $sur] = $tenant->run(fn () => [makeSede('Norte'), makeSede('Sur')]);

    $this->post(portal($tenant, 'radicar'), validSubmission([
        'sede_id' => $norte->id,
        'subject' => '=HYPERLINK("https://malo.example","Clic")',
    ]))->assertSessionHasNoErrors();
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $sur->id, 'subject' => 'Solicitud del sur']))
        ->assertSessionHasNoErrors();

    [$admin, $agente] = $tenant->run(fn () => [User::first(), makeStaff('agente', sedes: [$norte])]);

    // Un agente no descarga: el archivo trae datos de todas las sedes.
    $this->actingAs($agente)->get(portal($tenant, 'admin/pqrs/exportar'))->assertForbidden();

    $response = $this->actingAs($admin)->get(portal($tenant, 'admin/pqrs/exportar?sede='.$norte->id.'&from='.now()->format('Y-m-d')));
    $response->assertOk()->assertDownload();
    ['rows' => $rows, 'xml' => $xml] = readXlsx($response->streamedContent());
    $header = $rows[0];
    $row = array_combine($header, $rows[1]);

    expect($rows)->toHaveCount(2)
        ->and($header)->toContain('Radicado', 'Sede', 'Cumplimiento', 'Paciente', 'EPS')->not->toContain('Empresa')
        ->and($row['Sede'])->toBe('Norte')
        ->and($row['Estado'])->toBe('Radicada')
        ->and($row['Cumplimiento'])->toBe('En plazo')
        ->and($row['Asunto'])->toBe('=HYPERLINK("https://malo.example","Clic")')
        // Lo que escribe el ciudadano nunca es una fórmula de Excel.
        ->and($xml)->not->toContain('<f>');

    // Fechas fuera de rango: el archivo sale solo con el encabezado.
    $empty = $this->actingAs($admin)->get(portal($tenant, 'admin/pqrs/exportar?to=2000-01-01'));
    expect(readXlsx($empty->streamedContent())['rows'])->toHaveCount(1);
});

/**
 * URL del panel central. Después de visitar el portal de una empresa,
 * route() arma las direcciones con el dominio de esa empresa.
 *
 * @param  array<string, mixed>  $parameters
 */
function central(string $name, array $parameters = []): string
{
    return rtrim((string) config('app.url'), '/').route($name, $parameters, false);
}

/** Super administrador con la contraseña ya confirmada (las rutas de PQRS la piden). */
function superAdmin(): User
{
    $user = User::factory()->create();
    test()->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    return $user;
}

test('the super admin sees each company pqrs list with its filters', function () {
    Notification::fake();
    $tenant = makeCompany('lista');
    [$norte, $sur] = $tenant->run(fn () => [makeSede('Norte'), makeSede('Sur')]);
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $norte->id, 'subject' => 'Del norte']));
    $this->post(portal($tenant, 'radicar'), validSubmission(['sede_id' => $sur->id, 'subject' => 'Del sur']));
    makeCompany('otra');
    tenancy()->end();

    // Sin confirmar la contraseña: ni los datos de los pacientes, ni
    // administrar empresas, ni cambiar el correo del perfil.
    session()->forget('auth.password_confirmed_at');
    $this->actingAs(User::factory()->create());
    $this->get(central('pqrs.index'))->assertRedirect(central('password.confirm'));
    $this->get(central('tenants.edit', ['tenant' => 'otra']))->assertRedirect(central('password.confirm'));
    $this->delete(central('tenants.destroy', ['tenant' => 'otra']))->assertRedirect(central('password.confirm'));
    $this->get(central('profile.edit'))->assertRedirect(central('password.confirm'));
    expect(Tenant::find('otra'))->not->toBeNull();

    superAdmin();

    $this->get(central('pqrs.index', ['empresa' => 'lista', 'sede' => (string) $sur->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pqrs/index')
            ->where('company', 'lista')
            ->has('companies', 2)
            ->where('total', 2)
            ->has('pqrs.data', 1)
            ->where('pqrs.data.0.subject', 'Del sur')
            ->has('sedes', 2));

    // Una empresa que no existe muestra la primera de la lista.
    $this->get(central('pqrs.index', ['empresa' => 'no-existe']))
        ->assertInertia(fn (Assert $page) => $page->where('company', 'lista'));
});

test('the super admin downloads one company or all of them in excel', function () {
    Notification::fake();
    $uno = makeCompany('uno');
    $dos = makeCompany('dos');
    $this->post(portal($uno, 'radicar'), validSubmission(['subject' => 'Solicitud de uno']));
    $this->post(portal($dos, 'radicar'), validSubmission(['subject' => 'Solicitud de dos']));
    tenancy()->end();
    superAdmin();

    $single = readXlsx($this->get(central('pqrs.export', ['empresa' => 'uno']))->assertOk()->streamedContent())['rows'];

    expect($single)->toHaveCount(2)
        ->and($single[0])->not->toContain('Empresa')
        ->and(array_combine($single[0], $single[1])['Asunto'])->toBe('Solicitud de uno');

    $all = readXlsx($this->get(central('pqrs.export', ['empresa' => 'todas', 'sede' => '99']))->assertOk()->streamedContent())['rows'];
    $companies = array_map(fn (array $row) => array_combine($all[0], $row)['Empresa'], array_slice($all, 1));

    // El filtro de sede no aplica a todas las empresas (cada una tiene las suyas).
    expect($all[0][0])->toBe('Empresa')
        ->and($companies)->toEqualCanonicalizing(['Empresa uno', 'Empresa dos']);

    // Una empresa que no existe no descarga las PQRS de otra.
    $this->get(central('pqrs.export', ['empresa' => 'no-existe']))->assertNotFound();
});

test('the super admin deletes pqrs with a reason, files included, and it stays on record', function () {
    Notification::fake();
    $tenant = makeCompany('borrado');
    $this->post(portal($tenant, 'radicar'), validSubmission([
        'subject' => 'Spam',
        'attachments' => [UploadedFile::fake()->create('soporte.pdf', 10, 'application/pdf')],
    ]))->assertSessionHasNoErrors();
    $this->post(portal($tenant, 'radicar'), validSubmission(['subject' => 'Real']))->assertSessionHasNoErrors();

    [$spam, $real, $files] = $tenant->run(fn () => [
        Pqrs::where('subject', 'Spam')->sole(),
        Pqrs::where('subject', 'Real')->sole(),
        Storage::disk('local')->allFiles('pqrs/'.Pqrs::where('subject', 'Spam')->value('radicado')),
    ]);
    expect($files)->toHaveCount(1);
    tenancy()->end();
    $admin = superAdmin();

    // Sin motivo, o con «otro» sin detalle, no se elimina nada.
    $this->delete(central('pqrs.destroy'), ['empresa' => 'borrado', 'ids' => [$spam->id]])->assertSessionHasErrors('reason');
    $this->delete(central('pqrs.destroy'), ['empresa' => 'borrado', 'ids' => [$spam->id], 'reason' => 'otro'])
        ->assertSessionHasErrors(['detail' => 'Cuenta brevemente el motivo.']);

    $this->delete(central('pqrs.destroy'), ['empresa' => 'borrado', 'ids' => [$spam->id], 'reason' => 'spam'])
        ->assertSessionHasNoErrors();

    $tenant->run(function () use ($spam, $real) {
        expect(Pqrs::find($spam->id))->toBeNull()
            ->and(Pqrs::find($real->id))->not->toBeNull()
            ->and(DB::table('pqrs_activities')->where('pqrs_id', $spam->id)->exists())->toBeFalse()
            ->and(DB::table('pqrs_attachments')->where('pqrs_id', $spam->id)->exists())->toBeFalse()
            ->and(Storage::disk('local')->allFiles("pqrs/{$spam->radicado}"))->toBe([]);
    });

    $record = PqrsDeletion::sole();

    expect($record->radicado)->toBe($spam->radicado)
        ->and($record->tenant_id)->toBe('borrado')
        ->and($record->reason)->toBe('spam')
        ->and($record->deleted_by)->toBe($admin->id);

    // El radicado eliminado ya no se encuentra en la consulta pública.
    $this->get(portal($tenant, 'consultar/'.$spam->radicado))->assertRedirect(portal($tenant, 'consultar'));
});

test('the browser gets the security headers', function () {
    $response = $this->get(route('login'))->assertOk();

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))
        ->toContain("frame-ancestors 'none'")
        ->toContain("script-src 'self' 'nonce-")
        ->toContain("object-src 'none'");

    config(['pqrs.security.csp' => 'enforce']);

    expect($this->get(route('login'))->headers->get('Content-Security-Policy'))->toContain("default-src 'self'");
});

test('old office files that can carry macros are rejected', function () {
    Notification::fake();
    $tenant = makeCompany('macros');

    foreach (['acta.doc' => 'application/msword', 'cuadro.xls' => 'application/vnd.ms-excel'] as $name => $mime) {
        $this->post(portal($tenant, 'radicar'), validSubmission([
            'attachments' => [UploadedFile::fake()->create($name, 10, $mime)],
        ]))->assertSessionHasErrors('attachments.0');
    }

    $this->post(portal($tenant, 'radicar'), validSubmission([
        'attachments' => [UploadedFile::fake()->create('acta.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')],
    ]))->assertSessionHasNoErrors();
});
