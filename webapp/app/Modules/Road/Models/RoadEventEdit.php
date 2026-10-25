<?php

namespace App\Modules\Road\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** The values an entry had before its maker edited it (shown as history). */
class RoadEventEdit extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'created_at' => 'datetime'];
    }
}
