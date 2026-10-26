<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tyre's trip to the retreader (M38): sent, and back with what it cost and the km it is good for again. */
class TyreRetread extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['sent_on' => 'date', 'returned_on' => 'date', 'cost' => 'decimal:2', 'expected_km' => 'integer'];
    }

    public function tyre(): BelongsTo
    {
        return $this->belongsTo(Tyre::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function getLabelAttribute(): string
    {
        return 'Retread from ' . $this->sent_on?->format('d M Y');
    }
}
