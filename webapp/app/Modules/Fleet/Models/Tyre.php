<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use App\Platform\Tenancy\HasBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One tyre of the register (M38), by its serial number: in the store, fitted
 * to a truck at a wheel position, away for retreading, or scrapped. Its cost
 * and expected km include every retread, so cost ÷ expected km is what each
 * km it runs costs.
 */
class Tyre extends Model
{
    use Audited, BelongsToCompany, HasBranch;

    public const STATUSES = ['in_store' => 'In store', 'fitted' => 'Fitted', 'retreading' => 'At the retreader', 'scrapped' => 'Scrapped'];

    public const KINDS = ['new' => 'New', 'retread' => 'Retreaded'];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['kind' => 'new', 'status' => 'in_store', 'km_run' => 0, 'retreads' => 0];

    protected function casts(): array
    {
        return ['purchased_on' => 'date', 'scrapped_on' => 'date', 'cost' => 'decimal:2', 'scrap_value' => 'decimal:2',
            'expected_km' => 'integer', 'km_run' => 'integer', 'retreads' => 'integer'];
    }

    /** A tyre with no branch is in the shared store. */
    public static function branchSharedWhenUnassigned(): bool
    {
        return true;
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function fitments(): HasMany
    {
        return $this->hasMany(TyreFitment::class)->orderByDesc('fitted_on')->orderByDesc('id');
    }

    /** The fitment it is on now, if fitted. */
    public function current(): HasOne
    {
        return $this->hasOne(TyreFitment::class)->whereNull('removed_on');
    }

    public function retreadings(): HasMany
    {
        return $this->hasMany(TyreRetread::class)->orderByDesc('sent_on')->orderByDesc('id');
    }

    /** What each km costs: the tyre's cost over the km expected of it; null without an expected km. */
    public function costPerKm(): ?float
    {
        return $this->expected_km ? round((float) $this->cost / $this->expected_km, 4) : null;
    }

    public function getLabelAttribute(): string
    {
        return trim($this->serial_no . ' ' . $this->brand);
    }
}
