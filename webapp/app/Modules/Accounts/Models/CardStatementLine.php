<?php

namespace App\Modules\Accounts\Models;

use App\Modules\Fleet\Models\Truck;
use App\Modules\Road\Models\RoadEvent;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One charge on a card statement (M44): open, matched to a crew entry, booked, or set aside. */
class CardStatementLine extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['open' => 'To look at', 'matched' => 'Matched', 'booked' => 'Booked', 'ignored' => 'Set aside'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['txn_at' => 'datetime', 'amount' => 'decimal:2'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(CardStatement::class, 'card_statement_id');
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(RoadEvent::class, 'road_event_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function getLabelAttribute(): string
    {
        return $this->kind . ' ' . $this->reference;
    }
}
