<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Platform\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the request's company into context, for signed-in people.
 *
 * The company comes from the session (chosen at sign-in, or switched from
 * the menu). It is used only if the person is still an active member and the
 * company can still be opened — a membership ended a minute ago ends access
 * on the next click. With exactly one company, it is chosen for them.
 *
 * This only resolves; RequireCompany decides what happens without one.
 */
class ResolveCompany
{
    public const SESSION_KEY = 'company_id';

    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = Auth::guard('web')->user();

        if ($account) {
            $member = $this->membership($account->id, $request->session()->get(self::SESSION_KEY));

            if (! $member) {
                $request->session()->forget(self::SESSION_KEY);

                $only = $account->activeMemberships()->limit(2)->get();
                if ($only->count() === 1) {
                    $member = $only->first();
                    $request->session()->put(self::SESSION_KEY, $member->company_id);
                }
            }

            if ($member) {
                $this->context->set($member->company, $member);

                // "Last seen" moves at most once every five minutes, not on every click.
                if (! $member->last_seen_at || $member->last_seen_at->lt(now()->subMinutes(5))) {
                    $member->forceFill(['last_seen_at' => now()])->saveQuietly();
                }
            }
        }

        try {
            return $next($request);
        } finally {
            // Long-running servers (Octane, queue workers) must not carry one
            // request's company into the next.
            $this->context->forget();
        }
    }

    private function membership(int $accountId, mixed $companyId): ?CompanyUser
    {
        if (! is_numeric($companyId)) {
            return null;
        }

        return CompanyUser::with('company')
            ->where('account_id', $accountId)
            ->where('company_id', (int) $companyId)
            ->where('status', 'active')
            ->whereHas('company', fn ($q) => $q->usable())
            ->first();
    }
}
