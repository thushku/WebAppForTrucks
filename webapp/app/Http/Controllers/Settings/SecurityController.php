<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Sign-in security: the company's two-step policy (a code by
 * email at sign-in: off, each person's choice, or always), and who is
 * locked out right now. A person in several companies gets the strictest
 * policy of them all (AuthenticationService::needsSecondFactor).
 */
class SecurityController extends Controller
{
    public const POLICIES = [
        'off'      => 'Off — password only',
        'optional' => 'Each person chooses',
        'always'   => 'Always — a code by email at every new browser',
    ];

    public function __construct(private CompanyContext $context) {}

    public function edit(): View
    {
        $accountIds = CompanyUser::where('company_id', $this->context->id())->where('status', '!=', 'left')->pluck('account_id');

        return view('settings.security', [
            'company'  => $this->context->company(),
            'policies' => self::POLICIES,
            'locked'   => Account::whereIn('id', $accountIds)
                ->where(fn ($q) => $q->where('locked_until', '>', now())->orWhereNotNull('locked_hard_at'))
                ->orderBy('name')->get(),
            'members'  => CompanyUser::where('company_id', $this->context->id())->whereIn('account_id', $accountIds)->pluck('id', 'account_id'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['mfa_policy' => ['required', Rule::in(array_keys(self::POLICIES))]]);

        $this->context->company()->forceFill(['mfa_policy' => $data['mfa_policy']])->save();

        return back()->with('status', __('Sign-in security saved.'));
    }
}
