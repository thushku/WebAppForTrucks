<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A bank statement file brought in for one bank ledger (M19). */
class BankStatement extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['period_from' => 'date', 'period_to' => 'date', 'closing_balance' => 'decimal:3'];
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->orderBy('line_date')->orderBy('id');
    }

    public function getLabelAttribute(): string
    {
        return (string) ($this->file_name ?: 'Bank statement #' . $this->id);
    }
}
