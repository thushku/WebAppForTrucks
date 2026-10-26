<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Accounts\Models\Voucher;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\People\Models\Person;
use App\Modules\Trips\Models\Trip;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A traffic fine or e-challan (M48): against a truck and the driver of that
 * moment, unpaid → paid (its voucher), disputed, or cancelled by the
 * authority. Borne by the company, or by the driver — then it comes off
 * their next pay run as a "fine" deduction (payslip_id).
 */
class Fine extends Model
{
    use Audited, BelongsToCompany;

    public const STATUSES = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'disputed' => 'Disputed', 'cancelled' => 'Cancelled'];

    public const BEARS = ['company' => 'The company', 'driver' => 'The driver, from their pay'];

    /** How it was paid: from the office, or by the crew on the road (already in the trip's expenses, no new entry). */
    public const PAID_MODES = ['cash' => 'Cash', 'bank' => 'Bank / UPI / card', 'road' => 'By the crew on the road'];

    /** Black points within 12 months that suspend a UAE licence. */
    public const POINTS_LIMIT = 24;

    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['status' => 'unpaid', 'bears' => 'company', 'points' => 0];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'due_on' => 'date', 'paid_on' => 'date', 'amount' => 'decimal:2', 'points' => 'integer'];
    }

    public function truck(): BelongsTo
    {
        return $this->belongsTo(Truck::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /** For lists (M22 branches): the fines on the person's branch's trucks, or of its people when no truck is named. */
    public function scopeVisible(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(fn ($w) => $w->whereIn('truck_id', Truck::inBranch()->select('id'))
            ->orWhere(fn ($p) => $p->whereNull('truck_id')->whereIn('person_id', Person::inBranch()->select('id'))));
    }

    /** Still to be dealt with: unpaid or disputed. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['unpaid', 'disputed'], true);
    }

    public function getLabelAttribute(): string
    {
        return trim(($this->challan_no ?: 'Fine') . ' ' . $this->issued_at?->format('d M Y'));
    }
}
