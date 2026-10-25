<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Console\Admin;
use App\Platform\Settings\PlatformSettings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a one-time code.
 *
 * Email always works. SMS and WhatsApp go through a provider (decision D21:
 * approved; India needs DLT registration). Until the provider is set up,
 * OTP_SMS_DRIVER=log writes the code to the application log — for
 * development only — and says so, rather than silently sending nothing.
 */
class CodeSender
{
    public function __construct(private PlatformSettings $settings) {}

    public function send(Account|Admin $subject, OtpChallenge $challenge, string $code): void
    {
        $brand   = $this->settings->brandName();
        $minutes = (int) ceil(OtpService::TTL_SECONDS / 60);

        if ($challenge->channel === 'email') {
            $this->email($subject->email, $code, $brand, $minutes);

            return;
        }

        $mobile = $subject instanceof Account ? $subject->mobile : null;
        $driver = (string) config('services.otp_sms.driver', 'log');

        if (! $mobile) {
            Log::warning('One-time code could not be delivered: no mobile number.', ['subject' => $challenge->subject_type . ':' . $challenge->subject_id]);

            return;
        }

        if ($driver === 'log') {
            // Development only: the code is readable in storage/logs.
            Log::info("[OTP:{$challenge->channel}] {$mobile} code {$code} ({$brand}, expires in {$minutes} min)");

            return;
        }

        // A real SMS / WhatsApp provider is plugged in here (provider class
        // chosen when the account with the provider is opened).
        Log::error("OTP SMS driver '{$driver}' is not installed; code not sent.", ['mobile' => Identifier::mask($mobile)]);
    }

    private function email(?string $to, string $code, string $brand, int $minutes): void
    {
        if (! $to) {
            Log::warning('One-time code could not be delivered: no email address.');

            return;
        }

        try {
            Mail::raw(
                "Your {$brand} code is {$code}\n\nIt expires in {$minutes} minutes. If you did not just try to sign in, "
                . 'ignore this message and change your password.',
                fn ($m) => $m->to($to)->subject("{$code} is your {$brand} code")
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
