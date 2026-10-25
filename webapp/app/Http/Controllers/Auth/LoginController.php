<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveCompany;
use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\AuthenticationService;
use App\Platform\Auth\Captcha;
use App\Platform\Auth\CodeSender;
use App\Platform\Auth\Identifier;
use App\Platform\Auth\LoginOutcome;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Auth\OtpService;
use App\Platform\Auth\TrustedBrowsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

/**
 * Sign in, the one-time-code step, choosing a company, and sign out.
 *
 * The service decides; this class only moves between screens and, on
 * success, starts the session (adapted from Clinic Ledger).
 */
class LoginController extends Controller
{
    public function __construct(
        private AuthenticationService $auth,
        private Captcha $captcha,
        private CodeSender $codes,
        private OtpService $otp,
        private TrustedBrowsers $trusted,
        private AuditRecorder $audit,
    ) {}

    public function show(Request $request): View
    {
        $login = (string) old('login', '');

        return view('auth.login', [
            'captcha' => $this->captcha->required($request->ip(), $login !== '' ? Identifier::normalise($login) : null),
        ]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login'    => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['nullable', 'boolean'],
            'captcha'  => ['nullable', 'string', 'max:12'],
        ]);

        $login = Identifier::normalise($data['login']);
        $ip    = $request->ip();

        if ($this->captcha->required($ip, $login)
            && ! $this->captcha->passes($request->session(), $data['captcha'] ?? null)) {
            $this->captcha->failed($ip, $login);

            return back()->withInput($request->only('login', 'remember'))
                ->withErrors(['captcha' => __('The characters did not match the picture. Try the new one.')]);
        }

        $outcome = $this->auth->attempt($login, $data['password'], $ip, $request->userAgent(), $request->cookie(TrustedBrowsers::COOKIE));

        return $this->respond($request, $outcome, $request->boolean('remember'));
    }

    /** The picture challenge, drawn fresh each time. */
    public function captcha(Request $request)
    {
        $code = $this->captcha->issue($request->session());

        return response($this->captcha->png($code))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, private');
    }

    // ---- One-time code ----------------------------------------------------

    public function showOtp(Request $request): View|RedirectResponse
    {
        $challenge = $this->pendingChallenge($request);

        return $challenge
            ? view('auth.otp', ['challenge' => $challenge])
            : redirect()->route('login')->with('error', __('Start signing in again.'));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'  => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
            'trust' => ['nullable', 'boolean'],
        ]);

        $challenge = $this->pendingChallenge($request);

        if (! $challenge) {
            return redirect()->route('login')->with('error', __('That code has expired. Sign in again.'));
        }

        $outcome = $this->auth->completeOtp($challenge, trim($data['code']), $request->ip(), $request->userAgent());

        if ($outcome->isSuccess()) {
            if ($request->boolean('trust')) {
                $token = $this->trusted->trust($outcome->account, $request->userAgent(), $request->ip());
                Cookie::queue(cookie(TrustedBrowsers::COOKIE, $token, 60 * 24 * TrustedBrowsers::DAYS, null, null, $request->isSecure(), true, false, 'lax'));
            }

            return $this->establish($request, $outcome->account, (bool) $request->session()->get('login.otp.remember', false));
        }

        if (! $challenge->fresh()?->isUsable()) {
            $request->session()->forget('login.otp');

            return redirect()->route('login')->with('error', __('That code can no longer be used. Sign in again.'));
        }

        return back()->withErrors(['code' => $outcome->message()]);
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $challenge = $this->pendingChallenge($request);
        $account   = $challenge ? Account::find($challenge->subject_id) : null;

        if (! $account) {
            return redirect()->route('login')->with('error', __('Start signing in again.'));
        }

        try {
            $issued = $this->otp->issue($account, 'login', $challenge->channel, $request->ip());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->put('login.otp.challenge_id', $issued['challenge']->id);
        $this->codes->send($account, $issued['challenge'], $issued['code']);

        return back()->with('status', __('We have sent a new code.'));
    }

    // ---- Companies ------------------------------------------------------------

    public function showChoice(Request $request): View|RedirectResponse
    {
        $memberships = $request->user()->activeMemberships()->get();

        if ($memberships->count() === 1) {
            $request->session()->put(ResolveCompany::SESSION_KEY, $memberships->first()->company_id);

            return redirect()->route('dashboard');
        }

        return view('auth.choose-company', ['memberships' => $memberships]);
    }

    /** Pick (or switch to) a company the person belongs to. */
    public function choose(Request $request): RedirectResponse
    {
        $data = $request->validate(['company' => ['required', 'uuid']]);

        $member = $request->user()->activeMemberships()->get()
            ->first(fn ($m) => $m->company->uuid === $data['company']);

        if (! $member) {
            return redirect()->route('company.choose')->with('error', __('You are not a member of that company.'));
        }

        $request->session()->put(ResolveCompany::SESSION_KEY, $member->company_id);
        $this->audit->record('company.opened', "{$request->user()->name} opened {$member->company->name}", 'company', (string) $member->company_id, $member->company->name, companyId: $member->company_id);

        return redirect()->route('dashboard');
    }

    // ---- Sign out -------------------------------------------------------------

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('You have signed out.'));
    }

    // ---- Moving between screens -----------------------------------------------

    private function respond(Request $request, LoginOutcome $outcome, bool $remember): RedirectResponse
    {
        switch ($outcome->status) {
            case 'success':
                return $this->establish($request, $outcome->account, $remember);

            case 'needs_otp':
                $request->session()->put('login.otp', [
                    'challenge_id' => $outcome->challenge->id,
                    'remember'     => $remember,
                    'expires'      => time() + 600,
                ]);
                $this->codes->send($outcome->account, $outcome->challenge, $outcome->plainCode);

                return redirect()->route('login.otp');

            default:
                return back()->withInput($request->only('login', 'remember'))
                    ->withErrors(['login' => $outcome->message()]);
        }
    }

    private function establish(Request $request, Account $account, bool $remember): RedirectResponse
    {
        Auth::guard('web')->login($account, $remember);

        $request->session()->regenerate();
        $request->session()->forget(['login.otp', 'captcha', ResolveCompany::SESSION_KEY]);
        // The splash with the progress bar shows once after signing in.
        $request->session()->put('splash', true);

        return redirect()->intended(route('company.choose'));
    }

    private function pendingChallenge(Request $request): ?OtpChallenge
    {
        $pending = $request->session()->get('login.otp');

        if (! $pending || ($pending['expires'] ?? 0) < time()) {
            return null;
        }

        $challenge = OtpChallenge::find($pending['challenge_id']);

        return $challenge && $challenge->purpose === 'login' && $challenge->subject_type === 'account' ? $challenge : null;
    }
}
