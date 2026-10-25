<?php

namespace App\Http\Middleware;

use App\Platform\Tenancy\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Screens that work inside a company: without one, the person picks one.
 *
 * Also the read-only rule: a lapsed trial or subscription reads everything
 * and writes nothing (data is kept, Trucksarthi parity) — except the pages
 * that let the owner renew or leave.
 */
class RequireCompany
{
    /** Writes still allowed while read-only: renewing, signing out, switching company. */
    private const ALLOWED_WHILE_READ_ONLY = ['logout', 'company.switch', 'billing.*', 'company.deletion.*'];

    public function __construct(private CompanyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            return $request->expectsJson()
                ? response()->json(['message' => __('Choose a company first.')], 409)
                : redirect()->route('company.choose');
        }

        $company = $this->context->company();

        if ($company->isReadOnly() && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALLOWED_WHILE_READ_ONLY)) {
            $message = __('This company is read-only because its plan has ended. Renew to make changes — nothing has been lost.');

            return $request->expectsJson()
                ? response()->json(['message' => $message], 423)
                : back()->with('error', $message);
        }

        return $next($request);
    }
}
