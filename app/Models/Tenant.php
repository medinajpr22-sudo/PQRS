<?php

namespace App\Models;

use App\Enums\PqrsStatus;
use App\Enums\UserRole;
use App\Support\TenantDefaults;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Empresa cliente de la plataforma. Cada una tiene su propia base de datos.
 *
 * @property string $id
 * @property string $name
 * @property string|null $nit
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property string $plan
 * @property string $radicado_prefix
 * @property array<string, mixed>|null $theme
 * @property array<string, mixed>|null $form_schema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const PLANS = ['basico', 'profesional', 'empresarial'];

    /**
     * Nombres que no se pueden usar para la base de datos de una empresa
     * (bases del sistema en MySQL/PostgreSQL y la base central).
     */
    public const RESERVED_DATABASE_NAMES = [
        'database', 'testing', 'mysql', 'sys', 'information_schema',
        'performance_schema', 'postgres', 'template0', 'template1',
    ];

    /**
     * Ejecuta código dentro de la base de datos de esta empresa.
     *
     * Igual que TenantRun::run() de stancl/tenancy, pero siempre vuelve al
     * contexto anterior aunque el código lance una excepción (p. ej. un error
     * de validación). Sin esto, la petición del panel central se quedaría
     * conectada a la base de la empresa y guardaría ahí su sesión.
     *
     * @template TReturn
     *
     * @param  callable(self): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        $originalTenant = tenant();

        tenancy()->initialize($this);

        try {
            return $callback($this);
        } finally {
            if ($originalTenant) {
                tenancy()->initialize($originalTenant);
            } else {
                tenancy()->end();
            }
        }
    }

    /**
     * Administradores de la empresa (viven en su propia base de datos).
     *
     * @return list<array{id: int, name: string, email: string, is_active: bool}>
     */
    public function companyAdmins(): array
    {
        return $this->run(fn () => array_values(User::query()
            ->where('role', UserRole::Admin)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'is_active' => (bool) $u->is_active,
            ])
            ->all()));
    }

    /**
     * Nombre real de la base de datos de esta empresa. Lo define el super
     * administrador al crearla; las empresas antiguas usan «tenant_<id>».
     */
    public function databaseName(): string
    {
        return (string) $this->database()->getName();
    }

    /**
     * ¿Ya hay una empresa con esa base de datos, o ya existe en el servidor?
     */
    public static function databaseNameInUse(string $name): bool
    {
        $centralDatabase = basename((string) config('database.connections.'.config('tenancy.database.central_connection').'.database'));

        if ($name === $centralDatabase || str_starts_with($centralDatabase, $name.'.')) {
            return true;
        }

        foreach (self::all() as $tenant) {
            if ($tenant instanceof self && $tenant->databaseName() === $name) {
                return true;
            }
        }

        return (new self)->database()->manager()->databaseExists($name);
    }

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'nit',
            'email',
            'phone',
            'status',
            'plan',
            'radicado_prefix',
            'theme',
            'form_schema',
            'created_at',
            'updated_at',
        ];
    }

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'form_schema' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Diseño completo (guardado + valores por defecto).
     *
     * @return array<string, mixed>
     */
    public function resolvedTheme(): array
    {
        $theme = array_merge(TenantDefaults::theme($this->name), $this->theme ?? []);
        $theme['logo_url'] = $theme['logo_path'] ? '/storage/'.$theme['logo_path'] : null;

        $font = TenantDefaults::FONTS[$theme['font']] ?? TenantDefaults::FONTS['circe-rounded'];
        $theme['font_family'] = $font['family'];
        $theme['font_url'] = $font['bunny'] ? 'https://fonts.bunny.net/css?family='.$font['bunny'].'&display=swap' : null;

        return $theme;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedFormSchema(): array
    {
        return TenantDefaults::mergeFormSchema($this->form_schema);
    }

    /**
     * Nombre de un tipo de solicitud tal como lo configuró la empresa.
     */
    public function pqrsTypeLabel(string $key): string
    {
        $types = $this->resolvedFormSchema()['types'] ?? [];

        foreach (is_array($types) ? $types : [] as $type) {
            if (is_array($type) && ($type['key'] ?? null) === $key) {
                return (string) ($type['label'] ?? $key);
            }
        }

        return $key;
    }

    public function primaryDomain(): ?string
    {
        return $this->domains->first()?->domain;
    }

    /**
     * URL pública del portal PQRS de la empresa (respeta esquema y puerto de APP_URL).
     */
    public function portalUrl(): ?string
    {
        $domain = $this->primaryDomain();

        return $domain ? self::urlForDomain($domain) : null;
    }

    public static function urlForDomain(string $domain): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return "{$scheme}://{$domain}{$port}";
    }

    /**
     * Dominio que se asigna automáticamente a partir del subdominio.
     */
    public static function subdomainToDomain(string $subdomain): string
    {
        return $subdomain.'.'.config('tenancy.central_domains')[0];
    }

    /**
     * Estadísticas de PQRS consultadas dentro de la BD de la empresa.
     *
     * @return array{total: int, open: int, overdue: int, this_month: int}
     */
    public function pqrsStats(): array
    {
        return $this->run(fn () => [
            'total' => Pqrs::count(),
            'open' => Pqrs::whereIn('status', PqrsStatus::openValues())->count(),
            'overdue' => Pqrs::overdue()->count(),
            'this_month' => Pqrs::where('created_at', '>=', now()->startOfMonth())->count(),
        ]);
    }
}
