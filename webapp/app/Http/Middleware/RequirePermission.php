<?php

namespace App\Http\Middleware;

use App\Platform\Authorization\PermissionCatalogue;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission check: `->middleware('permission:trip.create')`
 * (ported from Clinic Ledger). Holding any one of the listed codes passes,
 * because "view_own OR view" is the common shape. What a person may do to a
 * particular record is checked again where the record is handled.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$codes): Response
    {
        // A typo in a route file must fail loudly at the source, not deny
        // everyone and get "fixed" by loosening something.
        foreach ($codes as $code) {
            if (! PermissionCatalogue::exists($code)) {
                throw new LogicException("Unknown permission '{$code}' on route '{$request->path()}'.");
            }
        }

        $account = Auth::user();

        if (! $account) {
            abort(401);
        }

        foreach ($codes as $code) {
            if ($account->hasPermission($code)) {
                return $next($request);
            }
        }

        abort(403, __('You do not have permission to do that.'));
    }
}
