<?php

namespace App\Modules\Trips\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A broker's commission on a trip, either way (blueprint 12 §5.1). */
class BrokerCommission extends Model
{
    use Audited, BelongsToCompany;

    public const DIRECTIONS = [
        'payable'    => 'We pay the broker (load broker)',
        'deducted'   => 'Taken from the truck supplier’s hire',
        'receivable' => 'We earn it (we acted as broker)',
    ];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'percent' => 'decimal:3'];
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'broker_party_id');
    }

    public function getLabelAttribute(): string
    {
        return 'Commission ' . $this->amount;
    }
}
