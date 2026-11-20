<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Una PQRS que eliminó un super administrador (base central). Ver la
 * migración create_pqrs_deletions_table.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $tenant_name
 * @property string $radicado
 * @property string $type
 * @property string $status
 * @property Carbon|null $filed_at
 * @property string $reason
 * @property string|null $detail
 * @property int|null $deleted_by
 * @property string $deleted_by_name
 * @property Carbon $created_at
 */
class PqrsDeletion extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
