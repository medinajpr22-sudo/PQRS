<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole|null $role Solo en usuarios de empresa (BD del tenant)
 * @property bool $is_active Solo en usuarios de empresa (BD del tenant)
 * @property Carbon|null $last_login_at Solo en usuarios de empresa (BD del tenant)
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Sede> $sedes
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Administrador de la empresa. (Los super administradores del panel
     * central no tienen rol: esto solo aplica dentro de un tenant.)
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAgent(): bool
    {
        return $this->role === UserRole::Agente;
    }

    /**
     * Sedes que atiende un agente (solo dentro de un tenant). Los
     * administradores no necesitan sedes: ven todas.
     *
     * @return BelongsToMany<Sede, $this>
     */
    public function sedes(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class);
    }

    /**
     * Sedes cuyas PQRS puede ver. `null` significa «todas» (administrador).
     *
     * @return list<int>|null
     */
    public function accessibleSedeIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return array_values(array_map('intval', $this->sedes->pluck('id')->all()));
    }

    /**
     * ¿Puede ver y gestionar las PQRS de esta sede? Las PQRS sin sede (las
     * radicadas antes de crear sedes) solo las ven los administradores.
     */
    public function canAccessSede(?int $sedeId): bool
    {
        $ids = $this->accessibleSedeIds();

        return $ids === null || ($sedeId !== null && in_array($sedeId, $ids, true));
    }

    /**
     * PQRS de las que es responsable (solo dentro de un tenant).
     *
     * @return HasMany<Pqrs, $this>
     */
    public function assignedPqrs(): HasMany
    {
        return $this->hasMany(Pqrs::class, 'assigned_to');
    }
}
