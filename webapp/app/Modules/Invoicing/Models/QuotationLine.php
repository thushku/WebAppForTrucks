<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Settings\Models\Place;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One route on a quotation — the same fields as a rate contract line, so it can become one. */
class QuotationLine extends Model
{
    use BelongsToCompany;

    /** The fields a rate contract line takes from it. */
    public const CONTRACT_FIELDS = ['from_place_id', 'to_place_id', 'material', 'truck_type', 'basis', 'rate', 'min_weight_mt',
        'detention_free_hours', 'detention_rate_per_day', 'loading_charge', 'unloading_charge'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2', 'min_weight_mt' => 'decimal:3', 'detention_rate_per_day' => 'decimal:2',
            'loading_charge' => 'decimal:2', 'unloading_charge' => 'decimal:2', 'detention_free_hours' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
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
