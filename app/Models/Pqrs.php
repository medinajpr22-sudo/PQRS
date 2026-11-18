<?php

namespace App\Models;

use App\Enums\PqrsStatus;
use App\Enums\RiskLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Solicitud PQRS (vive en la base de datos de cada empresa).
 *
 * @property int $id
 * @property string $radicado
 * @property string $type
 * @property int|null $sede_id
 * @property RiskLevel|null $risk_level Solo en reclamos de empresas de salud
 * @property PqrsStatus $status
 * @property string $channel
 * @property string $requester_name
 * @property string|null $requester_document_type
 * @property string|null $requester_document
 * @property string $requester_email
 * @property string|null $requester_phone
 * @property string $subject
 * @property string $description
 * @property array<int, array{key: string, label: string, value: mixed}>|null $extra_fields
 * @property int|null $assigned_to
 * @property Carbon|null $due_at
 * @property Carbon|null $responded_at
 * @property Carbon|null $closed_at
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Pqrs extends Model
{
    protected $table = 'pqrs';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PqrsStatus::class,
            'risk_level' => RiskLevel::class,
            'extra_fields' => 'array',
            'due_at' => 'datetime',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'radicado';
    }

    /**
     * @return HasMany<PqrsActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(PqrsActivity::class)->latest('id');
    }

    /**
     * @return HasMany<PqrsAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PqrsAttachment::class);
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Solo las PQRS que este usuario puede ver: todas si es administrador;
     * las de sus sedes si es agente.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $ids = $user->accessibleSedeIds();

        if ($ids !== null) {
            $query->whereIn('sede_id', $ids);
        }
    }

    /**
     * Primero los reclamos de riesgo vital, luego los priorizados y después el resto.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOrderByRisk(Builder $query): void
    {
        $query->orderByRaw("case risk_level when 'vital' then 0 when 'priorizado' then 1 else 2 end");
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', PqrsStatus::openValues());
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->whereIn('status', PqrsStatus::openValues())->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen() && $this->due_at !== null && $this->due_at->isPast();
    }
}
