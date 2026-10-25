<?php

namespace App\Modules\Road\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** One location from a crew phone. Written in batches, never edited. */
class GpsPoint extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'received_at' => 'datetime', 'lat' => 'float', 'lng' => 'float', 'speed_kmh' => 'float', 'mock' => 'boolean'];
    }
}
