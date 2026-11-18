<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantDefaults;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Crea una empresa completa: registro central, base de datos propia,
 * dominio y usuario administrador inicial.
 */
class TenantProvisioner
{
    /**
     * @param  array{
     *     id: string,
     *     name: string,
     *     nit?: string|null,
     *     email?: string|null,
     *     phone?: string|null,
     *     plan?: string,
     *     radicado_prefix?: string|null,
     *     domain: string,
     *     database?: string|null,
     *     theme?: array<string, mixed>,
     *     form_schema?: array<string, mixed>,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string,
     * }  $data
     */
    public function create(array $data): Tenant
    {
        $attributes = [
            'id' => $data['id'],
            'name' => $data['name'],
            'nit' => $data['nit'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => Tenant::STATUS_ACTIVE,
            'plan' => $data['plan'] ?? 'basico',
            'radicado_prefix' => Str::upper($data['radicado_prefix'] ?? '') ?: self::defaultPrefix($data['name']),
            'theme' => array_merge(TenantDefaults::theme($data['name']), $data['theme'] ?? []),
            'form_schema' => TenantDefaults::mergeFormSchema($data['form_schema'] ?? null),
        ];

        // Nombre de la base de datos que definió el super administrador. Si no
        // se indica, stancl/tenancy usa «tenant_<id>».
        if (filled($data['database'] ?? null)) {
            $attributes[Tenant::internalPrefix().'db_name'] = $data['database'];
        }

        $tenant = new Tenant($attributes);

        // Nunca reutilizar una base existente: al revertir una creación fallida
        // se borraría la base de otra empresa.
        if (Tenant::databaseNameInUse($tenant->databaseName())) {
            throw ValidationException::withMessages([
                'database' => "Ya existe una base de datos llamada «{$tenant->databaseName()}».",
            ]);
        }

        try {
            // Al guardar el tenant, stancl/tenancy crea y migra su base de datos (TenancyServiceProvider).
            $tenant->save();

            $tenant->domains()->create(['domain' => $data['domain']]);

            $tenant->run(function () use ($data) {
                $user = new User([
                    'name' => $data['admin_name'],
                    'email' => Str::lower(trim($data['admin_email'])),
                    'password' => $data['admin_password'],
                ]);
                $user->email_verified_at = now();
                $user->role = UserRole::Admin;
                $user->save();
            });
        } catch (Throwable $e) {
            // Si algo falla, no dejamos una empresa a medio crear (la base, si
            // alcanzó a crearse, es nueva: se comprobó arriba que no existía).
            if ($tenant->exists) {
                try {
                    $tenant->delete();
                } catch (Throwable) {
                    // Se conserva el error original.
                }
            }

            throw $e;
        }

        return $tenant;
    }

    public static function defaultPrefix(string $name): string
    {
        $words = collect(preg_split('/\s+/', Str::ascii($name)) ?: [])
            ->filter(fn ($w) => strlen($w) > 2)
            ->take(3);

        $prefix = $words->count() >= 2
            ? $words->map(fn ($w) => $w[0])->implode('')
            : substr((string) $words->first(), 0, 3);

        return Str::upper($prefix ?: 'PQR');
    }
}
