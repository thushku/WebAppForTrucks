<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A GSTIN or TRN of a party, with the state and billing address it belongs to. */
class PartyTaxId extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['is_default' => false, 'active' => true];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'active' => 'boolean'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->tax_id;
    }
}
