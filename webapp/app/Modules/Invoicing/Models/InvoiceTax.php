<?php

namespace App\Modules\Invoicing\Models;

use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** A tax part of a bill at one rate: CGST 9 % on ₹50,000 = ₹4,500 — charged, or notional under reverse charge. */
class InvoiceTax extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['percent' => 'decimal:6', 'taxable' => 'decimal:3', 'amount' => 'decimal:3', 'charged' => 'boolean'];
    }

    public function label(): string
    {
        return $this->code . ' ' . \App\Modules\Tax\Services\TaxCalculator::pct((float) $this->percent) . '%';
    }
}
