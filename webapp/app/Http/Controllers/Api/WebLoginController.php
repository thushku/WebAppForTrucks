<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Platform\Auth\WebLoginQr;
use App\Platform\Tenancy\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The owner app approves a web sign-in it has scanned (see WebLoginQr).
 * The browser opens the company the phone is working in (X-Company).
 *
 * The app sends either the token or the whole scanned link.
 */
class WebLoginController extends Controller
{
    /**
     * Before approving, the app shows where the browser is ("Chrome on
     * Windows, 49.36.x.x, 1 minute ago") so a code shown by somebody else's
     * computer is not approved by mistake.
     */
    public function inspect(Request $request, WebLoginQr $qr): JsonResponse
    {
        $token = $this->token($request);
        $row   = $token ? $qr->find($token) : null;

        if (! $row || $row->approved_at !== null || now()->gte($row->expires_at)) {
            return response()->json(['message' => __('That code has expired. Refresh the sign-in page and scan again.'), 'code' => 'expired'], 422);
        }

        return response()->json([
            'browser'    => WebLoginQr::describeBrowser((string) $row->user_agent),
            'ip'         => $row->ip,
            'created_at' => \Illuminate\Support\Carbon::parse($row->created_at)->toIso8601String(),
        ]);
    }

    public function approve(Request $request, WebLoginQr $qr, CompanyContext $context): JsonResponse
    {
        $token = $this->token($request);

        if (! $token) {
            return response()->json(['message' => __('That is not a sign-in code.'), 'code' => 'unknown'], 422);
        }

        $result = $qr->approve($token, $request->user(), $context->member());

        return $result === 'approved'
            ? response()->json(['ok' => true, 'message' => __('The browser is signing in to :company.', ['company' => $context->company()->name])])
            : response()->json(['message' => match ($result) {
                'expired' => __('That code has expired. Refresh the sign-in page and scan again.'),
                'used'    => __('That code was already used.'),
                default   => __('That is not a sign-in code.'),
            }, 'code' => $result], 422);
    }

    /** The token from the scanned link (or the bare token). */
    private function token(Request $request): ?string
    {
        $code  = (string) $request->validate(['code' => ['required', 'string', 'max:500']])['code'];
        $token = preg_match('#/qr/([A-Za-z0-9]{40})$#', $code, $m) ? $m[1] : $code;

        return preg_match('/^[A-Za-z0-9]{40}$/', $token) ? $token : null;
    }
}
