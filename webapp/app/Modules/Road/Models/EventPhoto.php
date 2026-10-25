<?php

namespace App\Modules\Road\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A photo of a road event (odometer, pump meter, receipt, POD…), with its fingerprints. */
class EventPhoto extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    public const SLOTS = ['odometer', 'pump_meter', 'receipt', 'fuel_gauge', 'slip', 'pod', 'damage', 'location', 'loaded', 'other'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime', 'created_at' => 'datetime', 'bytes' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(RoadEvent::class, 'road_event_id');
    }
}
