<?php

namespace App\Platform\Billing;

use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A company's plan and its life (blueprint 04 §4, decision D20): the console
 * records a subscription (online payment comes when a gateway is approved),
 * extends a trial, suspends and resumes; every night the ended trials and
 * subscriptions turn read-only — data stays, writes stop until renewed
 * (Trucksarthi parity). Every step is in the audit log.
 */
class Billing
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * Put a company on a plan from a date. The running subscription ends the
     * day before; the price comes from the plan (and the trucks for a
     * per-truck plan), less the coupon.
     */
    public function subscribe(Company $company, Plan $plan, string $period, CarbonImmutable $from, ?int $trucks = null, ?string $couponCode = null, ?string $reference = null, ?string $notes = null, ?int $adminId = null): Subscription
    {
        if (! $plan->active) {
            throw ValidationException::withMessages(['plan_id' => __('This plan is switched off.')]);
        }
        $coupon = $couponCode ? Coupon::where('code', strtoupper(trim($couponCode)))->first() : null;
        if ($couponCode && ! $coupon) {
            throw ValidationException::withMessages(['coupon' => __('There is no coupon :code.', ['code' => strtoupper(trim($couponCode))])]);
        }
        if ($coupon && ($why = $coupon->refusal($plan, $from))) {
            throw ValidationException::withMessages(['coupon' => $why]);
        }
        if ($period === 'lifetime' && $plan->pricing !== 'lifetime' || $period !== 'lifetime' && $plan->pricing === 'lifetime') {
            throw ValidationException::withMessages(['period' => __('The :plan plan is bought :how.', ['plan' => $plan->name, 'how' => $plan->pricing === 'lifetime' ? __('once, for life') : __('monthly or yearly')])]);
        }
        $trucks = $plan->pricing === 'per_truck' ? max(1, (int) ($trucks ?? 1)) : null;
        $price = $plan->price($period, $trucks ?? 1);
        $discount = $coupon ? $coupon->discountOn($price) : 0.0;

        return DB::transaction(function () use ($company, $plan, $period, $from, $trucks, $coupon, $price, $discount, $reference, $notes, $adminId) {
            Subscription::where('company_id', $company->id)->where('status', 'active')->lockForUpdate()->get()
                ->each(fn (Subscription $s) => $s->forceFill(['status' => 'ended', 'ends_on' => $s->ends_on && $s->ends_on->lt($from) ? $s->ends_on : $from->subDay()])->save());

            $subscription = Subscription::create([
                'company_id' => $company->id, 'plan_id' => $plan->id, 'period' => $period, 'status' => 'active',
                'starts_on' => $from->toDateString(),
                'ends_on' => match ($period) { 'lifetime' => null, 'annual' => $from->addYear()->subDay()->toDateString(), default => $from->addMonthNoOverflow()->subDay()->toDateString() },
                'trucks' => $trucks, 'amount' => round($price - $discount, 2), 'discount' => $discount, 'currency' => $plan->currency,
                'coupon_id' => $coupon?->id, 'reference' => $reference, 'notes' => $notes, 'created_by_admin' => $adminId,
            ]);
            if ($coupon) {
                $coupon->increment('used_count');
            }
            $company->forceFill(['plan_id' => $plan->id, 'status' => 'active', 'read_only_since' => null, 'suspended_reason' => null])->save();

            $this->audit->record('billing.subscribed', "Put {$company->name} on {$plan->name} ({$period}) from {$from->toDateString()}: {$subscription->amount} {$plan->currency}",
                'company', (string) $company->id, $company->name, severity: 'notable', companyId: $company->id);

            return $subscription;
        });
    }

    public function extendTrial(Company $company, int $days, string $reason): void
    {
        $from = $company->trial_ends_at && $company->trial_ends_at->isFuture() ? CarbonImmutable::instance($company->trial_ends_at) : CarbonImmutable::now();
        $company->forceFill(['trial_ends_at' => $from->addDays($days), 'status' => $company->status === 'active' ? 'active' : 'trial', 'read_only_since' => null])->save();
        $this->audit->record('billing.trial_extended', "Extended {$company->name}'s trial by {$days} days, to {$company->trial_ends_at->toDateString()}", 'company', (string) $company->id, $company->name,
            reason: $reason, severity: 'notable', companyId: $company->id);
    }

    public function suspend(Company $company, string $reason): void
    {
        $company->forceFill(['status' => 'suspended', 'suspended_reason' => mb_substr($reason, 0, 255)])->save();
        $this->audit->record('billing.suspended', "Suspended {$company->name}", 'company', (string) $company->id, $company->name, reason: $reason, severity: 'sensitive', companyId: $company->id);
    }

    /** Back to what the dates say: active on a running subscription, on trial while it lasts, else read-only. */
    public function resume(Company $company): void
    {
        $company->forceFill(['status' => $this->statusFor($company), 'suspended_reason' => null])->save();
        $company->forceFill(['read_only_since' => $company->status === 'lapsed' ? ($company->read_only_since ?? now()) : null])->save();
        $this->audit->record('billing.resumed', "Resumed {$company->name} ({$company->status})", 'company', (string) $company->id, $company->name, severity: 'notable', companyId: $company->id);
    }

    /**
     * The nightly sweep: subscriptions past their end date end; companies
     * whose trial or subscription has run out become read-only.
     *
     * @return int how many companies turned read-only
     */
    public function sweep(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        Subscription::where('status', 'active')->whereNotNull('ends_on')->where('ends_on', '<', $today->toDateString())->update(['status' => 'ended', 'updated_at' => now()]);

        $lapsed = 0;
        foreach (Company::whereIn('status', ['trial', 'active'])->get() as $company) {
            if ($this->statusFor($company, $today) === 'lapsed') {
                $company->forceFill(['status' => 'lapsed', 'read_only_since' => now()])->save();
                $this->audit->record('billing.lapsed', "{$company->name} is read-only: its " . ($company->plan_id ? 'plan' : 'trial') . ' has ended', 'company', (string) $company->id, $company->name, severity: 'notable', companyId: $company->id);
                $lapsed++;
            }
        }

        return $lapsed;
    }

    public function current(Company $company): ?Subscription
    {
        return Subscription::with('plan')->where('company_id', $company->id)->where('status', 'active')->orderByDesc('starts_on')->first();
    }

    private function statusFor(Company $company, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $running = Subscription::where('company_id', $company->id)->where('status', 'active')->where('starts_on', '<=', $today->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today->toDateString()))->exists();

        return match (true) {
            $running => 'active',
            $company->trial_ends_at && $company->trial_ends_at->gte($today) => 'trial',
            default => 'lapsed',
        };
    }
}
