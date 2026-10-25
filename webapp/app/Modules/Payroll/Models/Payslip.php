<?php

namespace App\Modules\Payroll\Models;

use App\Modules\People\Models\Person;
use App\Platform\Audit\Audited;
use App\Platform\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's pay in a run: the basis and how many days, trips or km at
 * what rate, extra lines the office adds while the run is a draft, the net,
 * and how it was paid.
 */
class Payslip extends Model
{
    use Audited, BelongsToCompany;

    public const ADDITIONS = ['bonus' => 'Bonus', 'overtime' => 'Overtime', 'allowance' => 'Allowance', 'other' => 'Other addition'];

    /** trip_cash and loan come off what the person holds or owes; fine and other are recovered as income. */
    public const DEDUCTIONS = ['trip_cash' => 'Trip cash recovered', 'loan' => 'Loan or advance recovered', 'fine' => 'Fine or damage', 'other' => 'Other deduction'];

    public const PAY_MODES = ['cash' => 'Cash', 'upi' => 'UPI', 'bank' => 'Bank transfer'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['lines' => 'array', 'units' => 'decimal:2', 'rate' => 'decimal:2', 'base_pay' => 'decimal:2', 'additions' => 'decimal:2', 'deductions' => 'decimal:2',
            'net' => 'decimal:2', 'paid_amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayRun::class, 'pay_run_id');
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** What the basis and units mean in words: "31 days of ₹22,000 a month", "6 trips at ₹1,500". */
    public function basisLabel(callable $money): string
    {
        return match ($this->pay_basis) {
            'per_trip' => trans_choice(':count trip at :rate|:count trips at :rate', (int) $this->units, ['rate' => $money($this->rate)]),
            'per_km' => __(':km km at :rate a km', ['km' => number_format((float) $this->units), 'rate' => $money($this->rate)]),
            default => trans_choice(':count day of :rate a month|:count days of :rate a month', (int) $this->units, ['rate' => $money($this->rate)]),
        };
    }

    public function getLabelAttribute(): string
    {
        // Never loads the person just for the log (strict mode forbids it inside a list).
        return __('Payslip of :name', ['name' => $this->relationLoaded('person') ? $this->person?->name : 'person #' . $this->person_id]);
    }
}
