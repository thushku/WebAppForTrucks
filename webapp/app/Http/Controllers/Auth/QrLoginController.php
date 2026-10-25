<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCompany;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\WebLoginQr;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The browser's side of "sign in with your phone" (see WebLoginQr). The poll
 * secret must match the one kept in this browser's session, so a secret
 * copied from the page cannot be used from another computer.
 */
class QrLoginController extends Controller
{
    private const SESSION_KEY = 'login.qr_poll';

    public function __construct(private WebLoginQr $qr, private AuditRecorder $audit) {}

    /** A new code for the sign-in page. */
    public function create(Request $request): JsonResponse
    {
        $issued = $this->qr->create($request->ip(), $request->userAgent());
        $request->session()->put(self::SESSION_KEY, hash('sha256', $issued['poll']));

        return response()->json([
            'svg'        => QrCode::encode(route('login.qr.scan', $issued['token']))->svg(2, '#0F1A2B', __('Sign-in code')),
            'poll'       => $issued['poll'],
            'expires_in' => WebLoginQr::LIFETIME_SECONDS,
        ])->header('Cache-Control', 'no-store');
    }

    public function poll(Request $request): JsonResponse
    {
        $poll = (string) $request->validate(['poll' => ['required', 'string', 'size:40']])['poll'];

        if (! hash_equals((string) $request->session()->get(self::SESSION_KEY), hash('sha256', $poll))) {
            return response()->json(['status' => 'expired']);
        }

        $result = $this->qr->collect($poll);

        if ($result === null) {
            return response()->json(['status' => 'waiting']);
        }
        if ($result === false) {
            return response()->json(['status' => 'expired']);
        }

        $account = $result['account'];
        Auth::guard('web')->login($account);
        $request->session()->regenerate();
        $request->session()->forget([self::SESSION_KEY, 'captcha']);
        $request->session()->put(ResolveCompany::SESSION_KEY, $result['company_id']);
        $request->session()->put('splash', true);
        $account->markLoggedIn($request->ip());
        $this->audit->record('login.qr', "{$account->name} signed in to the web app with the phone", 'account', (string) $account->id, $account->name, companyId: $result['company_id']);

        return response()->json(['status' => 'approved', 'redirect' => route('dashboard')]);
    }

    /** A phone camera that opens the code as a link lands here. */
    public function scan(): View
    {
        return view('auth.qr-scan');
    }
}
