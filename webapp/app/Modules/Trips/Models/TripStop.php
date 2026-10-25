<?php

namespace App\Modules\Trips\Models;

use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A stop on a trip, in order: where to load, unload, fuel or cross a border. */
class TripStop extends Model
{
    use BelongsToCompany;

    public const KINDS = ['load' => 'Load', 'unload' => 'Unload', 'both' => 'Load and unload', 'fuel' => 'Fuel', 'border' => 'Border / checkpost', 'other' => 'Other'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['seq' => 'integer', 'planned_at' => 'datetime', 'arrived_at' => 'datetime', 'departed_at' => 'datetime'];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
