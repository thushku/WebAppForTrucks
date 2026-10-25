<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Platform\Auth\Captcha;
use App\Platform\Auth\CodeSender;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Console\Admin;
use App\Platform\Console\AdminAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Console sign-in: password, picture challenge, then the mandatory email code.
 */
class AuthController extends Controller
{
    public function __construct(
        private AdminAuthService $auth,
        private Captcha $captcha,
        private CodeSender $codes,
    ) {}

    public function show(Request $request): View
    {
        // The console always shows the picture, whatever the app's setting.
        return view('platform.login', ['captcha' => true]);
    }

    public function attempt(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'string', 'email:filter', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
            'captcha'  => ['required', 'string', 'max:12'],
        ]);

        if (! $this->captcha->passes($request->session(), $data['captcha'])) {
            $this->captcha->failed($request->ip(), $data['email']);

            return back()->withInput($request->only('email'))
                ->withErrors(['captcha' => __('The characters did not match the picture. Try the new one.')]);
        }

        $result = $this->auth->attempt($data['email'], $data['password'], $request->ip(), $request->userAgent());

        if ($result['status'] === 'needs_otp') {
            $request->session()->put('platform.otp', ['challenge_id' => $result['challenge']->id, 'expires' => time() + 600]);
            $this->codes->send($result['admin'], $result['challenge'], $result['code']);

            return redirect()->route('platform.otp');
        }

        $message = match ($result['status']) {
            'locked'         => $result['until']
                ? __('Too many attempts. Try again later.')
                : __('This console login is locked. Unlock it on the server with: php artisan platform:unlock'),
            'too_many_codes' => __('Too many codes were asked for. Wait a few minutes, then sign in again.'),
            default          => __('Those details do not match a console login.'),
        };

        return back()->withInput($request->only('email'))->withErrors(['email' => $message]);
    }

    public function showOtp(Request $request): View|RedirectResponse
    {
        $challenge = $this->pending($request);

        return $challenge
            ? view('platform.otp', ['challenge' => $challenge])
            : redirect()->route('platform.login')->with('error', __('Start signing in again.'));
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data      = $request->validate(['code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/']]);
        $challenge = $this->pending($request);

        if (! $challenge) {
            return redirect()->route('platform.login')->with('error', __('That code has expired. Sign in again.'));
        }

        $admin = $this->auth->completeOtp($challenge, trim($data['code']), $request->ip(), $request->userAgent());

        if (! $admin) {
            return $challenge->fresh()?->isUsable()
                ? back()->withErrors(['code' => __('That code is not valid or has expired.')])
                : redirect()->route('platform.login')->with('error', __('That code can no longer be used. Sign in again.'));
        }

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();
        $request->session()->forget(['platform.otp', 'captcha']);

        return redirect()->route('platform.home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->with('status', __('You have signed out of the console.'));
    }

    private function pending(Request $request): ?OtpChallenge
    {
        $pending = $request->session()->get('platform.otp');

        if (! $pending || ($pending['expires'] ?? 0) < time()) {
            return null;
        }

        $challenge = OtpChallenge::find($pending['challenge_id']);

        return $challenge && $challenge->subject_type === 'admin' && $challenge->purpose === 'login' ? $challenge : null;
    }
}
