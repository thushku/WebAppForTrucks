<?php

namespace App\Platform\Website;

use App\Platform\Billing\Coupon;
use App\Platform\Billing\Plan;
use Carbon\CarbonImmutable;

/**
 * The pricing the website shows (decision D20: from the super-admin's
 * plans, nothing in code): the public plans of a country with their monthly,
 * annual and lifetime prices, what the annual price saves, and a coupon's
 * effect on each plan — or why the coupon does not apply.
 */
class Pricing
{
    public const COUNTRIES = ['IN' => 'India', 'AE' => 'United Arab Emirates'];

    /**
     * @return array{plans: array<int, array>, coupon: ?Coupon, couponError: ?string}
     */
    public function table(string $country, ?string $couponCode = null): array
    {
        $plans = Plan::offered($country)->where('is_public', true)->get();
        $coupon = null;
        $error = null;
        if (filled($couponCode)) {
            $coupon = Coupon::where('code', strtoupper(trim((string) $couponCode)))->first();
            $error = $coupon ? null : __('There is no coupon :code.', ['code' => strtoupper(trim((string) $couponCode))]);
            // A coupon that fits no plan on show says why (the first plan's reason).
            if ($coupon && $plans->every(fn (Plan $p) => $coupon->refusal($p, CarbonImmutable::today()) !== null)) {
                $error = $plans->isEmpty() ? null : $coupon->refusal($plans->first(), CarbonImmutable::today());
            }
        }

        return [
            'plans' => $plans->map(fn (Plan $plan) => $this->row($plan, $coupon))->all(),
            'coupon' => $coupon && ! $error ? $coupon : null,
            'couponError' => $error,
        ];
    }

    private function row(Plan $plan, ?Coupon $coupon): array
    {
        $applies = $coupon && $coupon->refusal($plan, CarbonImmutable::today()) === null;
        $price = function (string $period) use ($plan, $coupon, $applies): ?array {
            $field = ['monthly' => 'price_monthly', 'annual' => 'price_annual', 'lifetime' => 'price_lifetime'][$period];
            if ($plan->{$field} === null) {
                return null;
            }
            $full = $plan->price($period);
            // An amount off comes off the bill, not off each truck: per-truck prices show it apart.
            $reduce = $applies && ($coupon->kind === 'percent' || $plan->pricing !== 'per_truck');

            return ['full' => $full, 'pay' => $reduce ? round($full - $coupon->discountOn($full), 2) : $full];
        };
        $monthly = $price('monthly');
        $annual = $price('annual');
        $saves = $monthly && $annual && $monthly['full'] > 0 ? (int) round((1 - $annual['full'] / ($monthly['full'] * 12)) * 100) : null;

        $free = collect([$monthly, $annual, $price('lifetime')])->filter()->every(fn ($p) => $p['full'] <= 0);

        return [
            'plan' => $plan, 'monthly' => $monthly, 'annual' => $annual, 'lifetime' => $price('lifetime'),
            // A coupon means nothing on a free plan.
            'saves' => $saves && $saves > 0 ? $saves : null, 'coupon' => $applies && ! $free, 'free' => $free,
        ];
    }
}
