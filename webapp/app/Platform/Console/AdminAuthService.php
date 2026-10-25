<?php

namespace App\Platform\Console;

use App\Platform\Audit\AuditRecorder;
use App\Platform\Auth\Captcha;
use App\Platform\Auth\LockLadder;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Auth\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Console sign-in: email + password, then ALWAYS a one-time email code.
 *
 * Same protections as company sign-in (same-time misses, captcha, lock
 * ladder), except that a hard-locked console login can only be opened from
 * the server (php artisan platform:unlock), never from the console itself.
 */
class AdminAuthService
{
    private static ?string $dummyHash = null;

    public function __construct(
        private OtpService $otp,
        private Captcha $captcha,
        private AuditRecorder $audit,
    ) {}

    /** @return array{status: string, admin?: Admin, challenge?: OtpChallenge, code?: string, until?: mixed} */
    public function attempt(string $email, string $password, ?string $ip, ?string $userAgent): array
    {
        $email = mb_strtolower(trim($email));
        $admin = Admin::where('email', $email)->first();

        if (! $admin) {
            Hash::check($password, $this->dummyHash());
            $this->captcha->failed($ip, $email);
            $this->record(null, $email, false, 'no_such_login', $ip, $userAgent);

            return ['status' => 'failed'];
        }

        if (LockLadder::isLocked($admin)) {
            Hash::check($password, $this->dummyHash());
            $this->record($admin, $email, false, 'locked', $ip, $userAgent);

            return ['status' => 'locked', 'until' => $admin->locked_hard_at ? null : $admin->locked_until];
        }

        if (! Hash::check($password, $admin->password_hash) || ! $admin->canLogIn()) {
            $this->captcha->failed($ip, $email);
            $lock = LockLadder::registerFailure($admin);
            $this->record($admin, $email, false, 'bad_password', $ip, $userAgent);

            if ($lock['locked']) {
                $this->audit->record('console.locked', "Console login {$email} locked after {$lock['attempts']} wrong passwords", 'admin', (string) $admin->id, $admin->name, severity: 'sensitive');
            }

            return $lock['locked']
                ? ['status' => 'locked', 'until' => $admin->locked_hard_at ? null : $admin->locked_until]
                : ['status' => 'failed'];
        }

        try {
            $issued = $this->otp->issue($admin, 'login', 'email', $ip);
        } catch (RuntimeException) {
            return ['status' => 'too_many_codes'];
        }

        return ['status' => 'needs_otp', 'admin' => $admin, 'challenge' => $issued['challenge'], 'code' => $issued['code']];
    }

    public function completeOtp(OtpChallenge $challenge, string $code, ?string $ip, ?string $userAgent): ?Admin
    {
        if ($challenge->subject_type !== 'admin' || ! $this->otp->verify($challenge, $code)) {
            return null;
        }

        $admin = Admin::find($challenge->subject_id);

        if (! $admin || ! $admin->canLogIn() || LockLadder::isLocked($admin)) {
            return null;
        }

        $admin->forceFill(['failed_attempts' => 0, 'last_login_at' => now(), 'last_login_ip' => $ip])->saveQuietly();
        $this->captcha->clear($ip, $admin->email);
        $this->record($admin, $admin->email, true, null, $ip, $userAgent, 'otp');

        return $admin;
    }

    private function record(?Admin $admin, string $email, bool $ok, ?string $reason, ?string $ip, ?string $ua, string $method = 'password'): void
    {
        try {
            DB::table('login_attempts')->insert([
                'subject_type'   => $admin ? 'admin' : 'unknown',
                'subject_id'     => $admin?->id,
                'identifier'     => mb_substr($email, 0, 255),
                'succeeded'      => $ok,
                'failure_reason' => $reason,
                'method'         => $method,
                'ip'             => $ip,
                'user_agent'     => $ua ? mb_substr($ua, 0, 255) : null,
                'attempted_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
