<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Historial de una PQRS: radicación, cambios de estado, notas internas y respuestas.
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int|null $user_id
 * @property string $type created|status_changed|note|response
 * @property string|null $body
 * @property bool $is_public
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 */
class PqrsActivity extends Model
{
    public const TYPE_CREATED = 'created';

    public const TYPE_STATUS = 'status_changed';

    public const TYPE_NOTE = 'note';

    public const TYPE_RESPONSE = 'response';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Pqrs, $this>
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(Pqrs::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<PqrsAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PqrsAttachment::class, 'activity_id');
    }
}
