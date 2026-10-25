<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
use App\Platform\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The company for a phone-app request (after auth:sanctum).
 *
 * The phone sends the company's public id in the X-Company header. A person
 * with one company may leave it out. A person with several must send it; the
 * crew app shows a company switcher (decision P7).
 *
 * A read-only company (its plan ended) reads but does not write, as on the
 * web: the phone keeps what it could not send in its queue and sends it once
 * the plan is renewed. Approving a web sign-in still works, so the owner can
 * reach the billing page.
 */
class ResolveApiCompany
{
    private const ALLOWED_WHILE_READ_ONLY = ['api.web-login.*'];

    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();
        $wanted  = (string) $request->header('X-Company', '');

        $query = CompanyUser::with('company')
            ->where('account_id', $account->id)
            ->where('status', 'active')
            ->whereHas('company', fn ($q) => $q->usable());

        $members = $wanted !== ''
            ? $query->whereHas('company', fn ($q) => $q->where('uuid', $wanted))->get()
            : $query->limit(2)->get();

        if ($members->count() !== 1) {
            return response()->json([
                'message' => $wanted === '' ? __('Choose a company first.') : __('You are not a member of that company.'),
                'code'    => $wanted === '' ? 'company_required' : 'company_forbidden',
            ], $wanted === '' ? 409 : 403);
        }

        $member = $members->first();
        if ($member->company->isReadOnly() && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALLOWED_WHILE_READ_ONLY)) {
            return response()->json([
                'message' => __('This company is read-only because its plan has ended. Nothing is lost: what you entered waits on the phone until the plan is renewed.'),
                'code'    => 'read_only',
            ], 423);
        }
        $this->context->set($member->company, $member);

        try {
            return $next($request);
        } finally {
            $this->context->forget();
        }
    }
}
