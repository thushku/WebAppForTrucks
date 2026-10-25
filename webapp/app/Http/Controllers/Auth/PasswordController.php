<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Identifier;
use App\Platform\Auth\PasswordTokens;
use App\Platform\Auth\TrustedBrowsers;
use App\Platform\Settings\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Forgot password" by email link, and setting the new password.
 *
 * The answer to "send me a link" is the same whether or not the address has
 * an account. A new password also: ends the lock, forgets trusted computers,
 * and signs out every phone (its tokens are deleted).
 */
class PasswordController extends Controller
{
    public function __construct(
        private PasswordTokens $tokens,
        private TrustedBrowsers $trusted,
        private PlatformSettings $settings,
        private AuditRecorder $audit,
    ) {}

    public function forgot(): View
    {
        return view('auth.forgot');
    }

    public function send(Request $request): RedirectResponse
    {
        $data    = $request->validate(['email' => ['required', 'email:filter', 'max:255']]);
        $account = Account::where('email', Identifier::normalise($data['email']))->first();

        if ($account && $account->canLogIn()) {
            $link  = route('password.reset', $this->tokens->issue('account', $account->id, 'reset'));
            $brand = $this->settings->brandName();

            try {
                Mail::raw(__("Someone asked to reset your :brand password.\n\nOpen this link to choose a new one (it works for 60 minutes):\n:link\n\nIf it was not you, ignore this message; your password stays the same.", ['brand' => $brand, 'link' => $link]),
                    fn ($m) => $m->to($account->email)->subject(__('Reset your :brand password', ['brand' => $brand])));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', __('If that address has an account, we have sent a link to reset the password.'));
    }

    public function edit(string $token): View|RedirectResponse
    {
        return $this->tokens->peek($token, 'reset')
            ? view('auth.reset', ['token' => $token])
            : redirect()->route('password.forgot')->with('error', __('That link has expired or was already used. Ask for a new one.'));
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'max:1024', 'confirmed', Password::defaults()],
        ]);

        $row = $this->tokens->consume($token, 'reset');

        if (! $row || $row->subject_type !== 'account' || ! ($account = Account::find($row->subject_id))) {
            return redirect()->route('password.forgot')->with('error', __('That link has expired or was already used. Ask for a new one.'));
        }

        DB::transaction(function () use ($account, $data) {
            $account->forceFill([
                'password_hash'     => Hash::make($data['password']),
                // Proving access to the mailbox also confirms the address.
                'email_verified_at' => $account->email_verified_at ?? now(),
            ])->save();
            $account->unlock();
            $account->tokens()->delete();
        });

        $this->trusted->forgetAll($account, 'password reset');
        $this->audit->record('account.password_reset', "{$account->name} set a new password by email link", 'account', (string) $account->id, $account->name, severity: 'notable');

        return redirect()->route('login')->with('status', __('Your new password is saved. Sign in with it.'));
    }
}
