<?php

namespace App\Platform\Auth;

use App\Platform\Auth\Models\PasswordToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single-use links: verify an email, reset a password, accept an invitation.
 *
 * The link carries a random token; only its SHA-256 is stored, so a copy of
 * the database cannot be turned into working links. Issuing a new link of
 * the same purpose retires the older ones.
 */
class PasswordTokens
{
    public const LIFETIME_MINUTES = ['verify' => 60 * 48, 'reset' => 60, 'invite' => 60 * 24 * 7];

    public function issue(string $subjectType, int $subjectId, string $purpose): string
    {
        $token = Str::random(48);

        DB::transaction(function () use ($subjectType, $subjectId, $purpose, $token) {
            PasswordToken::where('subject_type', $subjectType)->where('subject_id', $subjectId)
                ->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);

            PasswordToken::create([
                'subject_type' => $subjectType,
                'subject_id'   => $subjectId,
                'purpose'      => $purpose,
                'token_hash'   => hash('sha256', $token),
                'expires_at'   => now()->addMinutes(self::LIFETIME_MINUTES[$purpose] ?? 60),
            ]);
        });

        return $token;
    }

    /** The live token row for a link, without using it up. */
    public function peek(string $token, string $purpose): ?PasswordToken
    {
        $row = PasswordToken::where('token_hash', hash('sha256', $token))->where('purpose', $purpose)->first();

        return $row && $row->isUsable() ? $row : null;
    }

    /** Use the link (once). Locks the row so two clicks cannot both succeed. */
    public function consume(string $token, string $purpose): ?PasswordToken
    {
        return DB::transaction(function () use ($token, $purpose) {
            $row = PasswordToken::where('token_hash', hash('sha256', $token))->where('purpose', $purpose)
                ->lockForUpdate()->first();

            if (! $row || ! $row->isUsable()) {
                return null;
            }

            $row->forceFill(['used_at' => now()])->save();

            return $row;
        });
    }
}
