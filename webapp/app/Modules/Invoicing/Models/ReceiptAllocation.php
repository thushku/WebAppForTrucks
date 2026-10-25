<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Part of a receipt applied to one bill: cash, TDS and deductions. */
class ReceiptAllocation extends Model
{
    use BelongsToCompany;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:3', 'tds' => 'decimal:3', 'deduction' => 'decimal:3', 'created_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function total(): float
    {
        return round((float) $this->amount + (float) $this->tds + (float) $this->deduction, 3);
    }
}
