<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Audit\AuditRecorder;
use App\Platform\Tenancy\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Sign-in for company users, by email (or mobile) and password.
 *
 * Adapted from Clinic Ledger. One password per person (the account), so a
 * person who works for two companies signs in once and then picks the
 * company. Nothing is revealed before the password is proved:
 *
 *  · an unknown login, a wrong password and a suspended login take the same
 *    time (a hash is always computed) and get the same message;
 *  · the lock ladder (LockLadder) stops a patient guesser on one login, the
 *    picture challenge (Captcha) stops a script trying many.
 */
class AuthenticationService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private OtpService $otp,
        private Captcha $captcha,
        private TrustedBrowsers $trusted,
        private AuditRecorder $audit,
    ) {}

    public function attempt(
        string $identifier,
        string $password,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $trustToken = null,
        string $country = 'IN',
    ): LoginOutcome {
        $identifier = Identifier::normalise($identifier, $country);
        $account    = $this->find($identifier);

        if (! $account) {
            $this->burn($password);
            $this->captcha->failed($ip, $identifier);
            $this->recordAttempt(null, $identifier, false, 'no_such_login', 'password', $ip, $userAgent);

            return LoginOutcome::failed('bad_credentials');
        }

        if (LockLadder::isLocked($account)) {
            $this->burn($password);
            $this->recordAttempt($account, $identifier, false, 'locked', 'password', $ip, $userAgent);

            return LoginOutcome::locked($account->locked_hard_at ? null : $account->locked_until);
        }

        if (! Hash::check($password, $account->password_hash ?: $this->dummyHash())) {
            $this->captcha->failed($ip, $identifier);
            $this->registerFailure($account, $ip);
            $this->recordAttempt($account, $identifier, false, 'bad_password', 'password', $ip, $userAgent);

            return LockLadder::isLocked($account)
                ? LoginOutcome::locked($account->locked_hard_at ? null : $account->locked_until)
                : LoginOutcome::failed('bad_credentials');
        }

        if (! $account->canLogIn()) {
            $this->recordAttempt($account, $identifier, false, 'inactive', 'password', $ip, $userAgent);

            return LoginOutcome::failed('bad_credentials');
        }

        // Right password, unconfirmed address: nobody signs in before the
        // link in the sign-up email has been opened.
        if (! $account->isVerified()) {
            $this->recordAttempt($account, $identifier, false, 'unverified', 'password', $ip, $userAgent);

            return LoginOutcome::failed('unverified');
        }

        if ($this->needsSecondFactor($account, $this->trusted->recognises($account, $trustToken))) {
            try {
                $issued = $this->otp->issue($account, 'login', 'email', $ip);
            } catch (RuntimeException) {
                return LoginOutcome::failed('too_many_codes');
            }

            return LoginOutcome::needsOtp($account, $issued['challenge'], $issued['code']);
        }

        return $this->succeed($account, $password, 'password', $ip, $userAgent);
    }

    /** Stage two: the code from the email. */
    public function completeOtp(\App\Platform\Auth\Models\OtpChallenge $challenge, string $code, ?string $ip = null, ?string $userAgent = null): LoginOutcome
    {
        if ($challenge->subject_type !== 'account' || ! $this->otp->verify($challenge, $code)) {
            return LoginOutcome::failed('invalid_code');
        }

        $account = Account::find($challenge->subject_id);

        if (! $account || ! $account->canLogIn() || LockLadder::isLocked($account)) {
            return LoginOutcome::failed('bad_credentials');
        }

        return $this->succeed($account, null, 'otp', $ip, $userAgent);
    }

    /**
     * The company's rule, and with "optional" the person's own switch. When a
     * person works for several companies, the strictest rule among them applies.
     */
    public function needsSecondFactor(Account $account, bool $trusted): bool
    {
        if ($trusted) {
            return false;
        }

        $policies = Company::query()
            ->whereIn('id', $account->memberships()->where('status', 'active')->select('company_id'))
            ->usable()
            ->pluck('mfa_policy');

        if ($policies->contains('always')) {
            return true;
        }

        return $policies->contains('optional') && $account->mfa_enabled;
    }

    /** The one place a sign-in is completed, whatever proved it. */
    public function succeed(Account $account, ?string $password, string $method, ?string $ip, ?string $userAgent): LoginOutcome
    {
        // Hash settings rise over time; an old hash is replaced while the
        // plain password is briefly in hand.
        if ($password !== null && $account->password_hash && Hash::needsRehash($account->password_hash)) {
            $account->forceFill(['password_hash' => Hash::make($password)])->saveQuietly();
        }

        $account->markLoggedIn($ip);
        $this->recordAttempt($account, (string) ($account->email ?? $account->mobile), true, null, $method, $ip, $userAgent);
        $this->captcha->clear($ip, $account->email ?? $account->mobile);

        return LoginOutcome::success($account);
    }

    public function find(string $identifier): ?Account
    {
        return Identifier::isEmail($identifier)
            ? Account::where('email', $identifier)->first()
            : Account::where('mobile', $identifier)->first();
    }

    private function registerFailure(Account $account, ?string $ip): void
    {
        $result = LockLadder::registerFailure($account);

        if (! $result['locked']) {
            return;
        }

        $opens = $result['minutes']
            ? "It unlocks by itself in {$result['minutes']} minutes."
            : 'It stays locked until your company owner or the platform support team unlocks it.';
        $where = $ip ? " from {$ip}" : '';

        $this->audit->record(
            'auth.locked',
            "{$account->name}'s login locked after {$result['attempts']} wrong passwords{$where}",
            'account', (string) $account->id, $account->name,
            severity: 'notable',
        );

        // A locked login is somebody who forgot their password, or somebody
        // else trying it. The person should know the same minute.
        if ($account->email) {
            try {
                Mail::raw(
                    "Your login was locked after {$result['attempts']} wrong passwords{$where}.\n\n{$opens} "
                    . 'If this was not you, change your password as soon as you can sign in again.',
                    fn ($m) => $m->to($account->email)->subject('Your login was locked')
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function recordAttempt(?Account $account, string $identifier, bool $ok, ?string $reason, string $method, ?string $ip, ?string $userAgent): void
    {
        try {
            DB::table('login_attempts')->insert([
                'subject_type'   => $account ? 'account' : 'unknown',
                'subject_id'     => $account?->id,
                'identifier'     => mb_substr($identifier, 0, 255),
                'succeeded'      => $ok,
                'failure_reason' => $reason,
                'method'         => $method,
                'ip'             => $ip,
                'user_agent'     => $userAgent ? mb_substr($userAgent, 0, 255) : null,
                'attempted_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Spend the time a real check would, so a miss looks like a wrong password. */
    private function burn(string $password): void
    {
        Hash::check($password, $this->dummyHash());
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
