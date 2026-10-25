<?php

namespace App\Platform\Auth;

/**
 * What a person types to sign in: an email address or a mobile number.
 *
 * Both are normalised the same way everywhere (sign-in, sign-up, lookups,
 * rate-limit keys), so "Ravi@Mail.com " and "ravi@mail.com" are one person,
 * and "98765 43210" in India is "+919876543210".
 */
final class Identifier
{
    /** Calling codes of the regions we sell in; the regional site decides the default. */
    public const COUNTRY_CODES = ['IN' => '91', 'AE' => '971'];

    public static function isEmail(string $value): bool
    {
        return str_contains($value, '@');
    }

    public static function normalise(string $value, string $country = 'IN'): string
    {
        $value = trim($value);

        return self::isEmail($value) ? mb_strtolower($value) : (self::mobile($value, $country) ?? $value);
    }

    /**
     * A mobile number in E.164 form, or null when it cannot be one.
     * India: 10 digits starting 6–9. UAE: 9 digits starting 5 (mobile).
     */
    public static function mobile(string $value, string $country = 'IN'): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $plus   = str_starts_with(trim($value), '+');
        $code   = self::COUNTRY_CODES[$country] ?? '91';

        if ($plus) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+' . $digits : null;
        }

        // A leading 0 (trunk prefix) is dropped; a number already carrying the
        // country code is accepted with or without the plus.
        $digits = ltrim($digits, '0');
        if (str_starts_with($digits, $code) && strlen($digits) > strlen($code) + 7) {
            $digits = substr($digits, strlen($code));
        }

        $valid = match ($country) {
            'IN'    => (bool) preg_match('/^[6-9]\d{9}$/', $digits),
            'AE'    => (bool) preg_match('/^5\d{8}$/', $digits),
            default => strlen($digits) >= 6 && strlen($digits) <= 12,
        };

        return $valid ? '+' . $code . $digits : null;
    }

    /** Masked for screens a stranger may see: "r•••@mail.com", "•••• 3210". */
    public static function mask(string $value): string
    {
        if (self::isEmail($value)) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

            return mb_substr($local, 0, 1) . str_repeat('•', max(mb_strlen($local) - 1, 1)) . '@' . $domain;
        }

        return '•••• ' . mb_substr($value, -4);
    }
}
