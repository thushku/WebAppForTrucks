<?php

namespace App\Modules\Trips\Models;

use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/** Money on a hire challan: an advance, fuel given, a deduction, the balance paid… */
class HireChallanLine extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['line_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function getLabelAttribute(): string
    {
        return $this->kind . ' ' . $this->amount;
    }
}
