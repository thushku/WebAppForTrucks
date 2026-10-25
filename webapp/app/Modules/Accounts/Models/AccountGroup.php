<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A group of the chart of accounts (Sundry debtors, Direct expenses…). Its
 * nature says where it reports: assets, liabilities and equity on the
 * balance sheet, income and expenses in the profit and loss.
 */
class AccountGroup extends Model
{
    use BelongsToCompany;

    public const NATURES = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Capital and equity', 'income' => 'Income', 'expense' => 'Expenses'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(LedgerAccount::class);
    }

    /** Debit-natured groups (assets, expenses) show a debit balance as positive. */
    public function isDebitNature(): bool
    {
        return in_array($this->nature, ['asset', 'expense'], true);
    }
}
