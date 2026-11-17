<?php

use App\Enums\PqrsStatus;
use App\Models\Pqrs;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PqrsReceivedNotification;
use App\Notifications\PqrsRespondedNotification;
use App\Services\TenantProvisioner;
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
