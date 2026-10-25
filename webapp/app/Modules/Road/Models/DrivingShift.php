<?php

namespace App\Modules\Road\Models;

use App\Modules\People\Models\Person;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Who held the wheel from when to when (blueprint 03 §7, decision D11). */
class DrivingShift extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime', 'start_odometer' => 'integer', 'end_odometer' => 'integer'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Trips\Models\Trip::class);
    }

    public function minutes(): int
    {
        return (int) $this->started_at->diffInMinutes($this->ended_at ?? now());
    }
}
