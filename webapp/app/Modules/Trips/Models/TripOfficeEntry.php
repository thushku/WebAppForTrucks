<?php

namespace App\Modules\Trips\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Income or an expense the office adds to a trip: a later workshop bill, the
 * broker's commission, office charges, a detention claim, an insurance or EMI
 * share. Allowed after the trip is closed (D8); every change is audited.
 * Cancelled with a reason, never deleted.
 */
class TripOfficeEntry extends Model
{
    use Audited, BelongsToCompany;

    public const KINDS = ['expense' => 'Expense', 'income' => 'Income'];

    /** Income kinds (expenses use the company's expense categories). */
    public const INCOME = [
        'detention'     => 'Detention / halting charges',
        'extra_freight' => 'Extra freight',
        'loading'       => 'Loading / unloading recovered',
        'commission'    => 'Commission earned',
        'claim'         => 'Insurance or damage claim',
        'other'         => 'Other income',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'entry_date' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('cancelled_at');
    }

    public function getLabelAttribute(): string
    {
        return ucfirst($this->kind) . ' ' . $this->category . ' ' . $this->amount;
    }
}
