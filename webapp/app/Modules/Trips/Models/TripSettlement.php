<?php

namespace App\Modules\Trips\Models;

use App\Modules\People\Models\Person;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A crew member's trip cash, settled (the "hisaab", blueprint 03 §8):
 * received (advances + money collected on the road) − spent from their own
 * pocket = balance. Positive: they hand it back; negative: the company pays
 * them. Bata is recorded here but goes to the personal ledger, not this cash.
 */
class TripSettlement extends Model
{
    use Audited, BelongsToCompany;

    public const MODES = [
        'nil'             => 'Nothing to settle',
        'cash_returned'   => 'Returned to the office',
        'paid_to_crew'    => 'Paid to the crew member',
        'carried_forward' => 'Carried to the next trip',
        'ledger'          => 'Adjusted in the personal ledger',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'advances' => 'decimal:2', 'collected' => 'decimal:2', 'spent' => 'decimal:2', 'balance' => 'decimal:2', 'bata_amount' => 'decimal:2',
            'bata_days' => 'integer', 'settled_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function getLabelAttribute(): string
    {
        return 'Settlement ' . $this->balance;
    }
}
