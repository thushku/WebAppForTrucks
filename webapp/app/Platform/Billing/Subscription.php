<?php

namespace App\Platform\Billing;

use App\Platform\Tenancy\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company on a plan from a date to a date (null: for life), with what was
 * charged, the coupon and the payment reference. Recorded by the console;
 * rows are not edited later — a change of plan ends one and starts another.
 */
class Subscription extends Model
{
    public const PERIODS = ['monthly' => 'Monthly', 'annual' => 'Annual', 'lifetime' => 'Lifetime'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'amount' => 'decimal:2', 'discount' => 'decimal:2'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
