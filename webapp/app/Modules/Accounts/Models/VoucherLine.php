<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A debit or a credit on one ledger, with its cost centres: the trip, truck,
 * branch and LR it belongs to (profit per trip and per truck come from these),
 * and on a crew member's ledger the bucket — trip cash or earnings.
 */
class VoucherLine extends Model
{
    use BelongsToCompany;

    public const BUCKETS = ['trip_cash' => 'Trip cash', 'earnings' => 'Earnings', 'loan' => 'Loan'];

    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('A voucher line is never edited.'));
        static::deleting(fn () => throw new RuntimeException('A voucher line is never deleted.'));
    }

    protected function casts(): array
    {
        return ['debit' => 'decimal:3', 'credit' => 'decimal:3'];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Trips\Models\Trip::class);
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Fleet\Models\Truck::class);
    }
}
