<?php

namespace App\Modules\Parties\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agreed rates with a party, versioned. Only an approved contract fills
 * trips and LRs; a draft can be changed freely, an approved one is changed
 * by making the next version (so old trips keep the rate they were made with).
 */
class RateContract extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['version' => 1, 'status' => 'draft'];

    protected function casts(): array
    {
        return [
            'valid_from'  => 'date',
            'valid_to'    => 'date',
            'approved_at' => 'datetime',
            'version'     => 'integer',
            'diesel_base_price' => 'decimal:2',
            'escalation_percent_per_rupee' => 'decimal:3',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RateContractLine::class);
    }

    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    /** "Rates 2026 (v2)" */
    public function getLabelAttribute(): string
    {
        return "{$this->name} (v{$this->version})";
    }
}
