<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * One balanced entry of the book (blueprint 07 §1). Posted, it is never
 * edited or deleted — enforced here, against code too: it is cancelled by an
 * exact reversal (a new voucher, dated in the open period), and only the
 * link to that reversal and the reason may still be written on it.
 */
class Voucher extends Model
{
    use Audited, BelongsToCompany;

    public const TYPES = [
        'sales' => 'Sales', 'credit_note' => 'Credit note', 'receipt' => 'Receipt', 'payment' => 'Payment', 'journal' => 'Journal',
        'contra' => 'Contra', 'expense' => 'Expense', 'purchase' => 'Purchase', 'reversal' => 'Reversal',
    ];

    /** Columns that may change after posting: the cancel, nothing else. */
    public const MUTABLE = ['status', 'reversed_by_voucher_id', 'cancel_reason', 'updated_at'];

    protected $guarded = ['id', 'company_id', 'uuid'];

    protected static function booted(): void
    {
        static::creating(function (Voucher $voucher) {
            $voucher->uuid ??= (string) Str::uuid7();
        });
        static::updating(function (Voucher $voucher) {
            $changed = array_diff(array_keys($voucher->getDirty()), self::MUTABLE);
            if ($changed !== []) {
                throw new RuntimeException('A posted voucher is never edited (' . implode(', ', $changed) . '). Cancel it: the system writes the reversal.');
            }
        });
        static::deleting(function () {
            throw new RuntimeException('A voucher is never deleted. Cancel it: the system writes the reversal.');
        });
    }

    protected function casts(): array
    {
        return ['voucher_date' => 'date', 'document_date' => 'date', 'total' => 'decimal:3'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class)->orderBy('sort_order');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_voucher_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_voucher_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account::class, 'created_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->voucher_type] ?? ucfirst($this->voucher_type));
    }
}
