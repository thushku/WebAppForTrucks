<?php

namespace App\Modules\Fleet\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Engine oil every 30,000 km or 180 days" for one truck. */
class ServiceSchedule extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['last_done_on' => 'date', 'every_km' => 'integer', 'every_days' => 'integer', 'last_done_km' => 'integer', 'active' => 'boolean'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function dueKm(): ?int
    {
        return $this->every_km && $this->last_done_km !== null ? $this->last_done_km + $this->every_km : null;
    }

    public function dueOn(): ?CarbonImmutable
    {
        return $this->every_days && $this->last_done_on ? CarbonImmutable::instance($this->last_done_on)->addDays($this->every_days) : null;
    }

    /**
     * overdue | soon | ok | unknown — "soon" within 10% of the interval or 15
     * days, whichever comes first.
     */
    public function state(?int $odometer): string
    {
        $dueKm = $this->dueKm();
        $dueOn = $this->dueOn();

        if ($dueKm === null && $dueOn === null) {
            return 'unknown';
        }
        if (($dueKm !== null && $odometer !== null && $odometer >= $dueKm) || ($dueOn && now()->startOfDay()->gte($dueOn))) {
            return 'overdue';
        }
        $soonKm = $dueKm !== null && $odometer !== null && $dueKm - $odometer <= max(500, (int) round($this->every_km * 0.1));
        $soonOn = $dueOn && now()->startOfDay()->diffInDays($dueOn, false) <= 15;

        return $soonKm || $soonOn ? 'soon' : 'ok';
    }
}
