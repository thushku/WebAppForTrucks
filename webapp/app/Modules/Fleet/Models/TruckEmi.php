<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Accounts\Models\Voucher;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One EMI of a truck's loan, paid (M35): its voucher takes the principal off the loan and books the interest. */
class TruckEmi extends Model
{
    use Audited, BelongsToCompany;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['due_on' => 'date', 'paid_on' => 'date', 'amount' => 'decimal:2', 'principal' => 'decimal:2', 'interest' => 'decimal:2'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function getLabelAttribute(): string
    {
        return 'EMI ' . $this->due_on?->format('M Y');
    }
}
