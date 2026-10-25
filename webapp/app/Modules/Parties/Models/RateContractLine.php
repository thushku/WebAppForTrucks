<?php

namespace App\Modules\Parties\Models;

use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One route rate in a contract: Kochi → Mumbai, cement, 12 wheels, ₹3,400 per tonne. */
class RateContractLine extends Model
{
    use BelongsToCompany;

    public const BASES = [
        'trip'    => 'per trip',
        'tonne'   => 'per tonne',
        'kg'      => 'per kg',
        'km'      => 'per km',
        'package' => 'per package',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2', 'min_weight_mt' => 'decimal:3', 'detention_rate_per_day' => 'decimal:2',
            'loading_charge' => 'decimal:2', 'unloading_charge' => 'decimal:2', 'extra_drop_charge' => 'decimal:2',
            'detention_free_hours' => 'integer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(RateContract::class, 'rate_contract_id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'from_place_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'to_place_id');
    }
}
