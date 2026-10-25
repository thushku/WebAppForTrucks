<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Auth\Models\OtpChallenge;
use Illuminate\Support\Carbon;

/**
 * The result of a sign-in attempt (ported from Clinic Ledger).
 *
 * A value object rather than a bool: "right password, now the code" and
 * "right password, which company?" are ordinary outcomes, and returning an
 * account object for those is how half-signed-in sessions happen.
 */
final class LoginOutcome
{
    private function __construct(
        public readonly string $status,          // success | needs_otp | failed | locked
        public readonly ?Account $account = null,
        public readonly ?OtpChallenge $challenge = null,
        /** needs_otp only, for the sender to deliver. Never persisted. */
        public readonly ?string $plainCode = null,
        public readonly ?string $reason = null,
        public readonly ?Carbon $lockedUntil = null,
    ) {}

    public static function success(Account $account): self
    {
        return new self('success', account: $account);
    }

    public static function needsOtp(Account $account, OtpChallenge $challenge, string $plainCode): self
    {
        return new self('needs_otp', account: $account, challenge: $challenge, plainCode: $plainCode);
    }

    public static function failed(string $reason): self
    {
        return new self('failed', reason: $reason);
    }

    public static function locked(?Carbon $until): self
    {
        return new self('locked', reason: 'locked', lockedUntil: $until);
    }

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * What the person is told. A wrong password, a suspended login and an
     * address that matches nobody all read the same; anything more specific
     * turns the sign-in form into a way to find out who has an account.
     */
    public function message(): string
    {
        return match ($this->status) {
            'success'   => __('Signed in.'),
            'needs_otp' => __('We have sent you a one-time code.'),
            'locked'    => $this->lockMessage(),
            default     => match ($this->reason) {
                'unverified'     => __('Confirm your email first: open the link we sent you when you signed up.'),
                'invalid_code'   => __('That code is not valid or has expired.'),
                'too_many_codes' => __('Too many codes were asked for. Wait a few minutes, then sign in again.'),
                'no_company'     => __('Your login is not part of any active company. Ask your company to invite you.'),
                default          => __('Those details do not match an account.'),
            },
        };
    }

    private function lockMessage(): string
    {
        if ($this->lockedUntil === null) {
            return __('This login is locked. Your company owner or the platform support team can unlock it.');
        }

        return now()->diffInMinutes($this->lockedUntil, false) > 20
            ? __('Too many attempts. This login is locked for an hour.')
            : __('Too many attempts. Try again in 15 minutes.');
    }
}
