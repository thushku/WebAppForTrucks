<?php

namespace App\Platform\Auth;

use App\Models\Account;
use App\Platform\Auth\Models\OtpChallenge;
use App\Platform\Console\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * One-time codes (ported from Clinic Ledger): the console's mandatory second
 * factor, the optional second factor for company users, and sign-in by
 * mobile OTP (decision D21).
 *
 * The plain code is returned to the caller for delivery and never stored —
 * a support person reading a live code out of the database is the same
 * failure as reading a password.
 */
class OtpService
{
    public const LENGTH = 6;

    public const TTL_SECONDS = 300;

    /** At most this many codes per subject and purpose in 15 minutes. */
    public const MAX_PER_WINDOW = 5;

    /** @return array{challenge: OtpChallenge, code: string} */
    public function issue(Account|Admin $subject, string $purpose = 'login', string $channel = 'email', ?string $ip = null): array
    {
        $type = $subject instanceof Admin ? 'admin' : 'account';

        $this->assertNotFlooding($type, $subject->id, $purpose);

        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        // Any earlier live code for the same purpose is retired: two valid
        // codes at once doubles the guessing surface.
        OtpChallenge::where('subject_type', $type)->where('subject_id', $subject->id)
            ->where('purpose', $purpose)->whereNull('consumed_at')->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        $destination = $channel === 'email' ? (string) $subject->email : (string) ($subject->mobile ?? '');

        $challenge = OtpChallenge::create([
            'subject_type'     => $type,
            'subject_id'       => $subject->id,
            'purpose'          => $purpose,
            'channel'          => $channel,
            'destination_hint' => $destination !== '' ? Identifier::mask($destination) : null,
            'code_hash'        => Hash::make($code),
            'expires_at'       => now()->addSeconds(self::TTL_SECONDS),
            'request_ip'       => $ip,
        ]);

        return ['challenge' => $challenge, 'code' => $code];
    }

    /** Verify and consume, under a row lock: two tabs with the same code must not both pass. */
    public function verify(OtpChallenge $challenge, string $code): bool
    {
        return DB::transaction(function () use ($challenge, $code) {
            $fresh = OtpChallenge::whereKey($challenge->id)->lockForUpdate()->first();

            if (! $fresh || ! $fresh->isUsable()) {
                return false;
            }

            $fresh->increment('attempts');

            if (! Hash::check(trim($code), $fresh->code_hash)) {
                return false;
            }

            $fresh->forceFill(['consumed_at' => now()])->save();

            return true;
        });
    }

    /** Without this, "resend code" is a free SMS gateway for anyone. */
    protected function assertNotFlooding(string $type, int $id, string $purpose): void
    {
        $recent = OtpChallenge::where('subject_type', $type)->where('subject_id', $id)
            ->where('purpose', $purpose)->where('created_at', '>', now()->subMinutes(15))->count();

        if ($recent >= self::MAX_PER_WINDOW) {
            throw new RuntimeException(__('Too many codes were asked for. Wait a few minutes, then try again.'));
        }
    }
}
