<?php

namespace Database\Seeders;

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Models\Pqrs;
use App\Models\PqrsActivity;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\BusinessDays;
use App\Support\TenantDefaults;
use Illuminate\Database\Seeder;

/**
 * Tres empresas de ejemplo, cada una con su propio diseño y formulario.
 */
class DemoTenantsSeeder extends Seeder
{
    public function run(TenantProvisioner $provisioner): void
    {
        foreach ($this->companies() as $company) {
            if (Tenant::find($company['id'])) {
                continue;
            }

            $tenant = $provisioner->create([
                ...$company,
                'domain' => Tenant::subdomainToDomain($company['id']),
                'admin_name' => 'Administrador '.$company['short'],
                'admin_email' => "admin@{$company['id']}.test",
                'admin_password' => 'Admin123*',
            ]);

            $tenant->run(function () use ($tenant, $company) {
                // Un funcionario de ejemplo para probar roles y asignaciones.
                $staff = new User([
                    'name' => 'Funcionario '.$company['short'],
                    'email' => "funcionario@{$company['id']}.test",
                    'password' => 'Admin123*',
                ]);
                $staff->forceFill([
                    'role' => UserRole::Funcionario,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ])->save();

                $this->seedRequests($tenant, $company['samples']);
            });
        }
    }

    /**
     * @param  list<array<string, mixed>>  $samples
     */
    protected function seedRequests(Tenant $tenant, array $samples): void
    {
        $agent = User::first();
        $schema = $tenant->resolvedFormSchema();

        foreach ($samples as $i => $sample) {
            $created = now()->subDays($sample['days_ago'])->setTime(9 + $i % 8, 15 * ($i % 4));
            $sla = collect($schema['types'])->firstWhere('key', $sample['type'])['sla_days'] ?? 15;

            $pqrs = Pqrs::create([
                'radicado' => sprintf('%s-%d-%05d', $tenant->radicado_prefix, $created->year, $i + 1),
                'type' => $sample['type'],
                'status' => $sample['status'],
                'requester_name' => $sample['name'],
                'requester_document_type' => 'CC',
                'requester_document' => (string) (1000000000 + $i * 7919),
                'requester_email' => $sample['email'],
                'requester_phone' => '300'.str_pad((string) (1234567 + $i), 7, '0'),
                'subject' => $sample['subject'],
                'description' => $sample['description'],
                'extra_fields' => collect($schema['fields'])->map(fn ($f) => [
                    'key' => $f['key'],
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'value' => $sample['extra'][$f['key']] ?? null,
                ])->all(),
                'due_at' => BusinessDays::add($created, $sla),
                'responded_at' => in_array($sample['status'], [PqrsStatus::Respondida, PqrsStatus::Cerrada], true) ? $created->addDays(3) : null,
                'closed_at' => $sample['status'] === PqrsStatus::Cerrada ? $created->addDays(5) : null,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $pqrs->activities()->create([
                'type' => PqrsActivity::TYPE_CREATED,
                'body' => 'Solicitud radicada a través del portal web.',
                'is_public' => true,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            if ($pqrs->status !== PqrsStatus::Radicada) {
                $pqrs->activities()->create([
                    'user_id' => $agent?->id,
                    'type' => PqrsActivity::TYPE_NOTE,
                    'body' => 'Se asigna al área responsable para revisión.',
                    'is_public' => false,
                    'created_at' => $created->addDay(),
                    'updated_at' => $created->addDay(),
                ]);
            }

            if ($pqrs->responded_at) {
                $pqrs->activities()->create([
                    'user_id' => $agent?->id,
                    'type' => PqrsActivity::TYPE_RESPONSE,
                    'body' => "Cordial saludo, {$pqrs->requester_name}.\n\nHemos revisado su solicitud y le informamos que fue atendida por el área correspondiente. Quedamos atentos a cualquier inquietud adicional.",
                    'is_public' => true,
                    'created_at' => $pqrs->responded_at,
                    'updated_at' => $pqrs->responded_at,
                ]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function companies(): array
    {
        $types = fn (array $enabled, array $overrides = []) => collect(TenantDefaults::formSchema()['types'])
            ->map(fn ($t) => [...$t, 'enabled' => in_array($t['key'], $enabled, true), ...($overrides[$t['key']] ?? [])])
            ->all();

        return [
            [
                'id' => 'andina',
                'short' => 'Andina',
                'name' => 'Aseguradora Andina S.A.',
                'nit' => '900.123.456-7',
                'email' => 'servicioalcliente@andina.test',
                'phone' => '601 744 0000',
                'plan' => 'empresarial',
                'radicado_prefix' => 'AND',
                'theme' => [
                    'layout' => 'corporate',
                    'primary_color' => '#0f3d91',
                    'accent_color' => '#f5a524',
                    'font' => 'inter',
                    'hero_title' => 'Defensoría y servicio al asegurado',
                    'hero_subtitle' => 'Radica tus peticiones, quejas y reclamos sobre pólizas y siniestros. Te respondemos dentro de los plazos de ley.',
                    'footer_text' => "Línea nacional 01 8000 123 456 · Bogotá 601 744 0000\nLunes a viernes de 7:00 a.m. a 7:00 p.m.",
                    'website_url' => 'https://andina.test',
                ],
                'form_schema' => [
                    'types' => $types(['peticion', 'queja', 'reclamo', 'sugerencia', 'felicitacion'], [
                        'reclamo' => ['description' => 'Inconformidad con el pago de un siniestro o la atención de una póliza.'],
                    ]),
                    'fields' => [
                        ['key' => 'numero_poliza', 'label' => 'Número de póliza', 'type' => 'text', 'required' => true, 'placeholder' => 'Ej: AU-2026-004512', 'help' => 'Lo encuentras en la carátula de tu póliza.', 'width' => 'half', 'options' => []],
                        ['key' => 'ramo', 'label' => 'Ramo del seguro', 'type' => 'select', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => ['Autos', 'Hogar', 'Vida', 'Salud', 'SOAT', 'Empresarial']],
                        ['key' => 'ciudad', 'label' => 'Ciudad', 'type' => 'text', 'required' => false, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => []],
                        ['key' => 'numero_siniestro', 'label' => 'Número de siniestro (si aplica)', 'type' => 'text', 'required' => false, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => []],
                    ],
                    'require_document' => true,
                    'require_phone' => true,
                    'allow_attachments' => true,
                    'max_attachments' => 5,
                ],
                'samples' => [
                    ['type' => 'reclamo', 'status' => PqrsStatus::EnTramite, 'days_ago' => 20, 'name' => 'Laura Gómez', 'email' => 'laura.gomez@example.com', 'subject' => 'Demora en el pago del siniestro de mi vehículo', 'description' => 'Hace más de un mes reporté el siniestro de mi vehículo y aún no recibo respuesta sobre el pago de la indemnización.', 'extra' => ['numero_poliza' => 'AU-2026-004512', 'ramo' => 'Autos', 'ciudad' => 'Bogotá']],
                    ['type' => 'peticion', 'status' => PqrsStatus::Respondida, 'days_ago' => 12, 'name' => 'Carlos Pérez', 'email' => 'carlos.perez@example.com', 'subject' => 'Solicitud de certificado de póliza', 'description' => 'Solicito el certificado de vigencia de mi póliza de hogar para un trámite bancario.', 'extra' => ['numero_poliza' => 'HO-2025-118734', 'ramo' => 'Hogar', 'ciudad' => 'Medellín']],
                    ['type' => 'queja', 'status' => PqrsStatus::Radicada, 'days_ago' => 2, 'name' => 'Andrea Ríos', 'email' => 'andrea.rios@example.com', 'subject' => 'Mala atención en la línea telefónica', 'description' => 'Llamé tres veces a la línea de atención y la llamada fue transferida sin solucionar mi inquietud.', 'extra' => ['numero_poliza' => 'VI-2024-000981', 'ramo' => 'Vida']],
                    ['type' => 'felicitacion', 'status' => PqrsStatus::Cerrada, 'days_ago' => 40, 'name' => 'Jorge Martínez', 'email' => 'jorge.martinez@example.com', 'subject' => 'Excelente atención del asesor', 'description' => 'Quiero felicitar al asesor que me atendió en la oficina de Cali por su amabilidad y rapidez.', 'extra' => ['ramo' => 'SOAT', 'ciudad' => 'Cali']],
                    ['type' => 'reclamo', 'status' => PqrsStatus::Radicada, 'days_ago' => 25, 'name' => 'Diana Torres', 'email' => 'diana.torres@example.com', 'subject' => 'Cobro doble de la prima', 'description' => 'En el extracto de mi tarjeta aparece un doble cobro de la prima mensual de mi póliza de salud.', 'extra' => ['numero_poliza' => 'SA-2026-002233', 'ramo' => 'Salud']],
                ],
            ],
            [
                'id' => 'santalucia',
                'short' => 'Santa Lucía',
                'name' => 'Clínica Santa Lucía',
                'nit' => '800.555.321-1',
                'email' => 'atencionalusuario@santalucia.test',
                'phone' => '604 444 1234',
                'plan' => 'profesional',
                'radicado_prefix' => 'CSL',
                'theme' => [
                    'layout' => 'modern',
                    'primary_color' => '#0e7c66',
                    'accent_color' => '#e11d48',
                    'font' => 'poppins',
                    'hero_title' => 'Oficina de atención al usuario',
                    'hero_subtitle' => 'Tu opinión nos ayuda a cuidar mejor de ti y de tu familia.',
                    'footer_text' => "Sede Norte · Sede Centro · Sede Sur\nAtención al usuario: 604 444 1234 ext. 200",
                ],
                'form_schema' => [
                    'types' => $types(['peticion', 'queja', 'reclamo', 'sugerencia', 'felicitacion', 'denuncia'], [
                        'queja' => ['sla_days' => 10],
                    ]),
                    'fields' => [
                        ['key' => 'sede', 'label' => 'Sede de atención', 'type' => 'select', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => ['Sede Norte', 'Sede Centro', 'Sede Sur']],
                        ['key' => 'fecha_atencion', 'label' => 'Fecha de la atención', 'type' => 'date', 'required' => false, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => []],
                        ['key' => 'servicio', 'label' => 'Servicio', 'type' => 'radio', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'full', 'options' => ['Urgencias', 'Consulta externa', 'Hospitalización', 'Laboratorio clínico', 'Imágenes diagnósticas']],
                        ['key' => 'eps', 'label' => 'EPS', 'type' => 'text', 'required' => false, 'placeholder' => 'Ej: Sura, Sanitas, Nueva EPS', 'help' => '', 'width' => 'half', 'options' => []],
                        ['key' => 'contacto_telefonico', 'label' => 'Deseo que me contacten telefónicamente', 'type' => 'checkbox', 'required' => false, 'placeholder' => '', 'help' => '', 'width' => 'full', 'options' => []],
                    ],
                    'require_document' => true,
                    'require_phone' => true,
                    'allow_attachments' => true,
                    'max_attachments' => 3,
                ],
                'samples' => [
                    ['type' => 'queja', 'status' => PqrsStatus::EnTramite, 'days_ago' => 6, 'name' => 'María Fernanda López', 'email' => 'mafe.lopez@example.com', 'subject' => 'Tiempo de espera en urgencias', 'description' => 'Esperé más de cinco horas en urgencias con mi hijo con fiebre alta sin recibir información.', 'extra' => ['sede' => 'Sede Norte', 'servicio' => 'Urgencias', 'eps' => 'Sura']],
                    ['type' => 'sugerencia', 'status' => PqrsStatus::Respondida, 'days_ago' => 15, 'name' => 'Pedro Castaño', 'email' => 'pedro.castano@example.com', 'subject' => 'Turnos virtuales para laboratorio', 'description' => 'Sugiero implementar turnos virtuales para la toma de muestras de laboratorio y evitar filas.', 'extra' => ['sede' => 'Sede Centro', 'servicio' => 'Laboratorio clínico']],
                    ['type' => 'felicitacion', 'status' => PqrsStatus::Cerrada, 'days_ago' => 30, 'name' => 'Luz Marina Ortiz', 'email' => 'luz.ortiz@example.com', 'subject' => 'Gracias al equipo de hospitalización', 'description' => 'Agradezco al personal de enfermería del tercer piso por el trato humano durante la hospitalización de mi madre.', 'extra' => ['sede' => 'Sede Sur', 'servicio' => 'Hospitalización']],
                    ['type' => 'reclamo', 'status' => PqrsStatus::Radicada, 'days_ago' => 1, 'name' => 'Santiago Mejía', 'email' => 'santiago.mejia@example.com', 'subject' => 'Resultados de imágenes no entregados', 'description' => 'Me realizaron una resonancia hace dos semanas y todavía no me entregan los resultados.', 'extra' => ['sede' => 'Sede Norte', 'servicio' => 'Imágenes diagnósticas', 'eps' => 'Sanitas']],
                ],
            ],
            [
                'id' => 'aguasdelvalle',
                'short' => 'Aguas del Valle',
                'name' => 'Aguas del Valle E.S.P.',
                'nit' => '890.300.111-2',
                'email' => 'pqr@aguasdelvalle.test',
                'phone' => '602 555 0101',
                'plan' => 'basico',
                'radicado_prefix' => 'ADV',
                'theme' => [
                    'layout' => 'minimal',
                    'primary_color' => '#0369a1',
                    'accent_color' => '#22c55e',
                    'font' => 'montserrat',
                    'hero_title' => 'PQR de servicios públicos',
                    'hero_subtitle' => 'Reporta daños, reclamos de facturación o solicitudes sobre tu servicio de acueducto y alcantarillado.',
                    'footer_text' => 'Línea de emergencias 116 · Oficina principal: Calle 10 # 5-20, Cali',
                ],
                'form_schema' => [
                    'types' => $types(['peticion', 'queja', 'reclamo'], [
                        'reclamo' => ['label' => 'Reclamo de facturación', 'description' => 'Inconformidad con el valor o el consumo facturado.'],
                    ]),
                    'fields' => [
                        ['key' => 'numero_contrato', 'label' => 'Número de contrato (cuenta)', 'type' => 'text', 'required' => true, 'placeholder' => 'Aparece en tu factura', 'help' => '', 'width' => 'half', 'options' => []],
                        ['key' => 'direccion_predio', 'label' => 'Dirección del predio', 'type' => 'text', 'required' => true, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => []],
                        ['key' => 'estrato', 'label' => 'Estrato', 'type' => 'select', 'required' => false, 'placeholder' => '', 'help' => '', 'width' => 'half', 'options' => ['1', '2', '3', '4', '5', '6', 'Comercial', 'Industrial']],
                    ],
                    'require_document' => true,
                    'require_phone' => false,
                    'allow_attachments' => true,
                    'max_attachments' => 2,
                ],
                'samples' => [
                    ['type' => 'reclamo', 'status' => PqrsStatus::EnTramite, 'days_ago' => 8, 'name' => 'Hernán Valencia', 'email' => 'hernan.valencia@example.com', 'subject' => 'Consumo facturado muy alto', 'description' => 'La factura de este mes llegó con un consumo tres veces mayor al habitual sin que hayamos cambiado nuestros hábitos.', 'extra' => ['numero_contrato' => '1002345', 'direccion_predio' => 'Cra 45 # 12-30', 'estrato' => '3']],
                    ['type' => 'peticion', 'status' => PqrsStatus::Radicada, 'days_ago' => 3, 'name' => 'Gloria Restrepo', 'email' => 'gloria.restrepo@example.com', 'subject' => 'Revisión de medidor', 'description' => 'Solicito una visita técnica para revisar el medidor, ya que parece estar girando sin consumo.', 'extra' => ['numero_contrato' => '1009876', 'direccion_predio' => 'Calle 5 # 20-11']],
                ],
            ],
        ];
    }
}
