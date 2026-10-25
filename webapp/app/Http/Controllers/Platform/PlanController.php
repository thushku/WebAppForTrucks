<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Billing\Coupon;
use App\Platform\Billing\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Plans and coupons (decision D20: set here, nothing fixed in code). A plan
 * in use is switched off, never deleted, so old subscriptions keep their
 * name and price.
 */
class PlanController extends Controller
{
    public function __construct(private AuditRecorder $audit) {}

    public function index(): View
    {
        return view('platform.plans', [
            'plans' => Plan::withCount(['subscriptions' => fn ($q) => $q->where('status', 'active')])->orderBy('sort_order')->orderBy('id')->get(),
            'coupons' => Coupon::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::create($this->planData($request));
        $this->audit->record('billing.plan_created', "Created plan {$plan->name}", 'plan', (string) $plan->id, $plan->name, severity: 'notable');

        return back()->with('status', __('Plan “:name” created.', ['name' => $plan->name]));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $plan->fill($this->planData($request, $plan))->save();
        $this->audit->record('billing.plan_changed', "Changed plan {$plan->name}", 'plan', (string) $plan->id, $plan->name, severity: 'notable');

        return back()->with('status', __('Plan “:name” saved.', ['name' => $plan->name]));
    }

    public function storeCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('coupons', 'code')],
            'kind' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'currency' => ['nullable', 'required_if:kind,fixed', 'string', 'size:3'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'plan_ids' => ['nullable', 'array'], 'plan_ids.*' => ['integer', Rule::exists('plans', 'id')],
        ]);
        if ($data['kind'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => __('A percentage is at most 100.')])->withInput();
        }
        $coupon = Coupon::create(['code' => strtoupper($data['code']), 'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null, 'plan_ids' => $data['plan_ids'] ?? null] + $data);
        $this->audit->record('billing.coupon_created', "Created coupon {$coupon->code}", 'coupon', (string) $coupon->id, $coupon->code, severity: 'notable');

        return back()->with('status', __('Coupon :code created.', ['code' => $coupon->code]));
    }

    public function toggleCoupon(Coupon $coupon): RedirectResponse
    {
        $coupon->forceFill(['active' => ! $coupon->active])->save();

        return back()->with('status', $coupon->active ? __('Coupon :code is on.', ['code' => $coupon->code]) : __('Coupon :code is off.', ['code' => $coupon->code]));
    }

    private function planData(Request $request, ?Plan $plan = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/', Rule::unique('plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(['IN', 'AE'])],
            'currency' => ['required', Rule::in(['INR', 'AED', 'USD'])],
            'pricing' => ['required', Rule::in(array_keys(Plan::PRICING))],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_annual' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'price_lifetime' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_if:pricing,lifetime'],
            'max_trucks' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'storage_mb' => ['nullable', 'integer', 'min:1'],
            'modules' => ['nullable', 'array'], 'modules.*' => [Rule::in(array_keys(Plan::MODULES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        if ($data['pricing'] !== 'lifetime' && ! isset($data['price_monthly']) && ! isset($data['price_annual'])) {
            abort(back()->withErrors(['price_monthly' => __('Give a monthly or an annual price.')])->withInput());
        }

        return ['modules' => empty($data['modules']) ? null : array_values($data['modules']), 'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_student' => $request->boolean('is_student'), 'is_public' => $request->boolean('is_public'), 'active' => $request->boolean('active')] + $data;
    }
}
