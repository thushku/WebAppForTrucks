<?php

namespace App\Platform\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * The lock ladder (website guideline §4; Clinic Ledger decision 63).
 *
 * Reaching the attempt limit locks the login for 15 minutes the first time,
 * an hour the second time, and the third time for good — until it is
 * unlocked by hand (the company owner for their own staff, or the
 * super-admin for anybody).
 *
 * Works on any model with failed_attempts / lock_level / locked_until /
 * locked_hard_at columns: accounts and console admins.
 */
final class LockLadder
{
    /** @return array{locked: bool, minutes: ?int, attempts: int} */
    public static function registerFailure(Model $who): array
    {
        $attempts = (int) $who->failed_attempts + 1;
        $limit    = max(3, (int) config('platform.lockout.attempts', 5));

        if ($attempts < $limit) {
            $who->forceFill(['failed_attempts' => $attempts])->saveQuietly();

            return ['locked' => false, 'minutes' => null, 'attempts' => $attempts];
        }

        $level   = min(3, (int) $who->lock_level + 1);
        $minutes = match ($level) {
            1       => (int) config('platform.lockout.first_minutes', 15),
            2       => (int) config('platform.lockout.second_minutes', 60),
            default => null,
        };

        $who->forceFill([
            'failed_attempts' => 0,
            'lock_level'      => $level,
            'locked_until'    => $minutes ? now()->addMinutes($minutes) : null,
            'locked_hard_at'  => $minutes ? null : now(),
        ])->saveQuietly();

        return ['locked' => true, 'minutes' => $minutes, 'attempts' => $attempts];
    }

    public static function isLocked(Model $who): bool
    {
        return $who->locked_hard_at !== null
            || ($who->locked_until !== null && $who->locked_until->isFuture());
    }
}
