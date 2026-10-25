<?php

namespace App\Modules\Accounts\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An account entries post to. A client, supplier, vendor, crew member or
 * bank account has its own (kind party / person / bank, made the first time
 * it is needed); the system's own carry a system key (cash, freight_income,
 * output_tax:CGST, expense:fuel…). A ledger with entries is never deleted.
 */
class LedgerAccount extends Model
{
    use Audited, BelongsToCompany;

    public const KINDS = ['general' => 'General', 'party' => 'Party', 'person' => 'Crew member', 'bank' => 'Bank', 'cash' => 'Cash', 'tax' => 'Tax'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:3', 'opening_date' => 'date', 'is_system' => 'boolean', 'active' => 'boolean'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class, 'account_group_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Parties\Models\Party::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\People\Models\Person::class);
    }
}
