<?php

namespace App\Models;

use App\Enums\PqrsStatus;
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

        $font = TenantDefaults::FONTS[$theme['font']] ?? TenantDefaults::FONTS['instrument-sans'];
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
