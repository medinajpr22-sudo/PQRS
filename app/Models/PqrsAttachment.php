<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo adjunto. Se guarda en el disco "local" de la empresa
 * (storage/tenant_<id>/app/...), nunca en una ruta pública.
 *
 * @property int $id
 * @property int $pqrs_id
 * @property int|null $activity_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 */
class PqrsAttachment extends Model
{
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Pqrs, $this>
     */
    public function pqrs(): BelongsTo
    {
        return $this->belongsTo(Pqrs::class);
    }

    /**
     * Actuación a la que pertenece el archivo (radicación, nota interna o respuesta).
     *
     * @return BelongsTo<PqrsActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(PqrsActivity::class, 'activity_id');
    }

    /**
     * Solo los archivos de actuaciones públicas (radicación y respuestas)
     * pueden verse desde el portal; los de notas internas nunca.
     */
    public function isPublic(): bool
    {
        return $this->activity?->is_public === true;
    }
}
