<?php

namespace App\Platform\Billing;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/** A discount code: a percentage or an amount off, between dates, a number of times, on some plans or all. */
class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valid_from' => 'date', 'valid_until' => 'date', 'plan_ids' => 'array', 'active' => 'boolean', 'value' => 'decimal:2'];
    }

    /** Why it cannot be used now on this plan, or null when it can. */
    public function refusal(Plan $plan, CarbonInterface $on): ?string
    {
        return match (true) {
            ! $this->active => __('This coupon is switched off.'),
            $this->valid_from && $on->lt($this->valid_from) => __('This coupon starts on :d.', ['d' => $this->valid_from->format('d M Y')]),
            $this->valid_until && $on->gt($this->valid_until->endOfDay()) => __('This coupon ended on :d.', ['d' => $this->valid_until->format('d M Y')]),
            $this->max_uses !== null && $this->used_count >= $this->max_uses => __('This coupon has been used up.'),
            ! empty($this->plan_ids) && ! in_array($plan->id, array_map('intval', $this->plan_ids), true) => __('This coupon is not for the :plan plan.', ['plan' => $plan->name]),
            $this->kind === 'fixed' && $this->currency && $this->currency !== $plan->currency => __('This coupon is in :c.', ['c' => $this->currency]),
            default => null,
        };
    }

    public function discountOn(float $price): float
    {
        $off = $this->kind === 'percent' ? $price * min(100, (float) $this->value) / 100 : (float) $this->value;

        return round(min($price, max(0, $off)), 2);
    }
}
