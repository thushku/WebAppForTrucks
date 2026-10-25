<?php

namespace App\Modules\Invoicing\Models;

use App\Modules\Trips\Models\Consignment;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One charge on a bill: the freight of an LR, loading, detention, toll recovered… with its tax as worked out. */
class InvoiceLine extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3', 'unit_rate' => 'decimal:3', 'discount_percent' => 'decimal:3', 'rate_percent' => 'decimal:6',
            'line_subtotal' => 'decimal:3', 'net' => 'decimal:3', 'taxable' => 'decimal:3', 'tax_amount' => 'decimal:3',
            'notional_tax' => 'decimal:3', 'line_total' => 'decimal:3', 'parts' => 'array',
        ];
    }

    public function consignment(): BelongsTo
    {
        return $this->belongsTo(Consignment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
