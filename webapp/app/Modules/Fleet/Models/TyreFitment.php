<?php

namespace App\Modules\Fleet\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tyre on a truck at one position, from the odometer it was fitted at to the one it came off at (M38). */
class TyreFitment extends Model
{
    use Audited, BelongsToCompany;

    /** Why a tyre comes off => [label, where it goes]. */
    public const REASONS = [
        'worn' => ['Worn out', 'scrapped'],
        'repair' => ['Puncture or repair', 'in_store'],
        'retread' => ['Sent for retreading', 'retreading'],
        'burst' => ['Burst or cut', 'scrapped'],
        'rotation' => ['Moved (rotation)', 'in_store'],
        'sold' => ['Sold', 'scrapped'],
        'other' => ['Other, back to the store', 'in_store'],
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['fitted_on' => 'date', 'removed_on' => 'date', 'fitted_odo' => 'integer', 'removed_odo' => 'integer', 'km' => 'integer'];
    }

    public function tyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->position . ' from ' . $this->fitted_on?->format('d M Y');
    }
}
