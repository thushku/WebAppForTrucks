<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Captcha;
use App\Platform\Auth\Identifier;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Settings\PlatformSettings;
use App\Platform\Signup\SignupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Sign-up (free trial, no card) and email verification.
 *
 * Nobody signs in before the link in the sign-up email is opened (website
 * guideline: "explicit, clickable email verification"). The answer to a
 * sign-up never says whether an address already has an account.
 */
class SignupController extends Controller
{
    public function __construct(
        private SignupService $signup,
        private PasswordTokens $tokens,
        private Captcha $captcha,
        private PlatformSettings $settings,
        private AuditRecorder $audit,
    ) {}

    public function show(): View
    {
        return view('auth.signup', ['trialDays' => $this->settings->trialDays()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company'  => ['required', 'string', 'min:2', 'max:150'],
            'country'  => ['required', Rule::in(array_keys(SignupService::COUNTRIES))],
            'name'     => ['required', 'string', 'min:2', 'max:150'],
            'email'    => ['required', 'string', 'email:filter', 'max:255'],
            'mobile'   => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::defaults()],
            'terms'    => ['accepted'],
            'captcha'  => ['required', 'string', 'max:12'],
        ]);

        if (! $this->captcha->passes($request->session(), $data['captcha'])) {
            return back()->withInput($request->except('password', 'password_confirmation', 'captcha'))
                ->withErrors(['captcha' => __('The characters did not match the picture. Try the new one.')]);
        }

        $email  = Identifier::normalise($data['email']);
        $mobile = filled($data['mobile'] ?? null) ? Identifier::mobile($data['mobile'], $data['country']) : null;

        if (filled($data['mobile'] ?? null) && ! $mobile) {
            return back()->withInput($request->except('password', 'password_confirmation', 'captcha'))
                ->withErrors(['mobile' => __('Enter a valid mobile number.')]);
        }

        // An address (or mobile) already in use: we do not say so on screen.
        // The owner of that address gets an email instead.
        $existing = Account::where('email', $email)->first();
        if ($existing || ($mobile && Account::where('mobile', $mobile)->exists())) {
            if ($existing) {
                $this->mail($email, __('Someone tried to sign up with your address'),
                    __('Someone tried to create a new company with your email address. If it was you, sign in instead — you can add another company from your account. If not, you can ignore this message.'));
            }

            return redirect()->route('signup.sent')->with('signup.email', $email);
        }

        $result = $this->signup->register([
            'company'  => $data['company'],
            'country'  => $data['country'],
            'name'     => $data['name'],
            'email'    => $email,
            'mobile'   => $mobile,
            'password' => $data['password'],
        ]);

        $this->sendVerification($result['account']);

        return redirect()->route('signup.sent')->with('signup.email', $email);
    }

    public function sent(Request $request): View
    {
        return view('auth.signup-sent', ['email' => $request->session()->get('signup.email')]);
    }

    /** The link in the email. */
    public function verify(string $token): RedirectResponse
    {
        $row = $this->tokens->consume($token, 'verify');

        if (! $row || $row->subject_type !== 'account' || ! ($account = Account::find($row->subject_id))) {
            return redirect()->route('login')->with('error', __('That link has expired or was already used. Sign in to get a new one.'));
        }

        if (! $account->email_verified_at) {
            $account->forceFill(['email_verified_at' => now()])->save();
            $this->audit->record('account.verified', "{$account->name} confirmed {$account->email}", 'account', (string) $account->id, $account->name);
        }

        return redirect()->route('login')->with('status', __('Your email is confirmed. Sign in to start.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $data    = $request->validate(['email' => ['required', 'email:filter', 'max:255']]);
        $account = Account::where('email', Identifier::normalise($data['email']))->first();

        if ($account && ! $account->email_verified_at) {
            $this->sendVerification($account);
        }

        // The same answer either way.
        return redirect()->route('signup.sent')->with('signup.email', Identifier::normalise($data['email']));
    }

    private function sendVerification(Account $account): void
    {
        $link  = route('signup.verify', $this->tokens->issue('account', $account->id, 'verify'));
        $brand = $this->settings->brandName();

        $this->mail($account->email, __('Confirm your email for :brand', ['brand' => $brand]),
            __("Welcome to :brand!\n\nOpen this link to confirm your email and start your free trial:\n:link\n\nThe link works for 48 hours.", ['brand' => $brand, 'link' => $link]));
    }

    private function mail(string $to, string $subject, string $body): void
    {
        try {
            Mail::raw($body, fn ($m) => $m->to($to)->subject($subject));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
