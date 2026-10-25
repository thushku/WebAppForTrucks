<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Parties\Models\Party;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A money receipt (blueprint 10 §6.2, §7): what a client paid, the TDS they
 * deducted and what else they kept (shortage, damage, late POD), applied to
 * bills. Gross settled = cash + TDS + deductions. What is not applied is the
 * client's advance credit. Numbered at once; voided, never deleted.
 */
class Receipt extends Model
{
    use Audited, BelongsToCompany;

    public const MODES = ['neft' => 'NEFT / IMPS', 'rtgs' => 'RTGS', 'upi' => 'UPI', 'cheque' => 'Cheque', 'cash' => 'Cash', 'card' => 'Card', 'other' => 'Other'];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected static function booted(): void
    {
        static::creating(function (Receipt $receipt) {
            $receipt->uuid ??= (string) Str::uuid7();
        });
    }

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date', 'cheque_date' => 'date', 'voided_at' => 'datetime',
            'amount' => 'decimal:3', 'tds_amount' => 'decimal:3', 'deduction_amount' => 'decimal:3', 'unapplied' => 'decimal:3',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class)->orderBy('id');
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** Cash + TDS + deductions: what it settles. */
    public function gross(): float
    {
        return round((float) $this->amount + (float) $this->tds_amount + (float) $this->deduction_amount, 3);
    }

    public function getLabelAttribute(): string
    {
        return $this->number;
    }
}
