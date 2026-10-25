<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Billing\Billing;
use App\Platform\Billing\Limits;
use App\Platform\Billing\Plan;
use App\Platform\Billing\Subscription;
use App\Platform\Tenancy\Company;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The console's companies (blueprint 04 §4): every tenant with its status,
 * plan, trucks used, trial end and last activity; one company's page to
 * put it on a plan, extend its trial, suspend or resume it.
 */
class CompanyController extends Controller
{
    public function __construct(private Billing $billing, private Limits $limits) {}

    public function index(Request $request): View
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(['trial', 'active', 'lapsed', 'suspended'])], 'q' => ['nullable', 'string', 'max:60']]);
        $companies = Company::with(['owner:id,name,email', 'plan:id,name'])
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(filled($f['q'] ?? null), fn ($q) => $q->where('name', 'like', '%' . addcslashes($f['q'], '%_\\') . '%'))
            ->orderByDesc('created_at')->paginate(50)->withQueryString();
        $ids = $companies->pluck('id');
        $trucks = DB::table('trucks')->whereIn('company_id', $ids)->where('status', '!=', 'sold')->groupBy('company_id')->selectRaw('company_id, count(*) as n')->pluck('n', 'company_id');
        $seen = DB::table('company_users')->whereIn('company_id', $ids)->groupBy('company_id')->selectRaw('company_id, max(last_seen_at) as at')->pluck('at', 'company_id');

        return view('platform.companies.index', ['companies' => $companies, 'trucks' => $trucks, 'seen' => $seen, 'filters' => $f,
            'counts' => Company::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')]);
    }

    public function show(Company $company): View
    {
        $company->load(['owner:id,name,email,mobile', 'plan']);

        return view('platform.companies.show', [
            'company' => $company, 'usage' => $this->limits->usage($company), 'current' => $this->billing->current($company),
            'subscriptions' => Subscription::with(['plan:id,name', 'coupon:id,code'])->where('company_id', $company->id)->orderByDesc('starts_on')->orderByDesc('id')->get(),
            'plans' => Plan::offered($company->country)->get(),
            'members' => $company->members()->with('account:id,name,email')->get(),
            'audit' => DB::table('audit_log')->where('company_id', $company->id)->where('action', 'like', 'billing.%')->orderByDesc('id')->limit(20)->get(['action', 'summary', 'reason', 'occurred_at']),
        ]);
    }

    public function subscribe(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'period' => ['required', Rule::in(array_keys(Subscription::PERIODS))],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'trucks' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'coupon' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $subscription = $this->billing->subscribe($company, Plan::findOrFail($data['plan_id']), $data['period'], CarbonImmutable::parse($data['starts_on']),
            $data['trucks'] ?? null, $data['coupon'] ?? null, $data['reference'] ?? null, $data['notes'] ?? null, $request->user('platform')->id);

        return back()->with('status', __(':company is on :plan from :date: :amount :currency.', ['company' => $company->name, 'plan' => $subscription->plan->name,
            'date' => $subscription->starts_on->format('d M Y'), 'amount' => number_format((float) $subscription->amount, 2), 'currency' => $subscription->currency]));
    }

    public function extendTrial(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365'], 'reason' => ['required', 'string', 'min:3', 'max:255']]);
        $this->billing->extendTrial($company, (int) $data['days'], $data['reason']);

        return back()->with('status', __('Trial extended to :date.', ['date' => $company->fresh()->trial_ends_at->format('d M Y')]));
    }

    public function suspend(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->billing->suspend($company, $data['reason']);

        return back()->with('status', __(':company is suspended: nobody can sign in to it.', ['company' => $company->name]));
    }

    public function resume(Company $company): RedirectResponse
    {
        $this->billing->resume($company);

        return back()->with('status', __(':company is back (:status).', ['company' => $company->name, 'status' => $company->fresh()->status]));
    }
}
