<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bank statement: open, matched to the book's entry on the bank
 * ledger, or ignored on purpose (with a note).
 */
class BankStatementLine extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['line_date' => 'date', 'amount' => 'decimal:3', 'balance' => 'decimal:3', 'matched_at' => 'datetime'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function voucherLine(): BelongsTo
    {
        return $this->belongsTo(VoucherLine::class);
    }
}
