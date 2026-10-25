<?php

namespace App\Support;

/**
 * Languages for screens and documents. The super-admin enables which ones a
 * customer can pick (decision D20); English and Hindi first. Documents have
 * their own language, independent of the screen (an Arabic invoice from an
 * English screen is normal).
 */
final class Locales
{
    /** Each language named in itself, for the pickers. */
    public const NATIVE = [
        'en' => 'English',
        'hi' => 'हिन्दी',
        'ml' => 'മലയാളം',
        'ta' => 'தமிழ்',
        'ur' => 'اردو',
        'ar' => 'العربية',
    ];

    public const RTL = ['ar', 'ur', 'he', 'fa'];

    /** @return string[] codes the screens can switch to (enabled by the super-admin) */
    public static function enabled(): array
    {
        $enabled = (array) app(\App\Platform\Settings\PlatformSettings::class)->get('enabled_languages');

        return array_values(array_intersect(array_keys(self::NATIVE), $enabled ?: ['en']));
    }

    public static function isRtl(?string $code): bool
    {
        return in_array($code, self::RTL, true);
    }
}
