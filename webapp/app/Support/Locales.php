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

    /**
     * Run $work in another screen language — a notice or an email in the
     * reader's own language (M36) — and come back to the current one.
     */
    public static function using(?string $code, \Closure $work): mixed
    {
        $before = app()->getLocale();
        $code = in_array($code, self::enabled(), true) ? $code : 'en';
        app()->setLocale($code);
        \Carbon\Carbon::setLocale($code);
        \Carbon\CarbonImmutable::setLocale($code);
        try {
            return $work();
        } finally {
            app()->setLocale($before);
            \Carbon\Carbon::setLocale($before);
            \Carbon\CarbonImmutable::setLocale($before);
        }
    }

    public static function isRtl(?string $code): bool
    {
        return in_array($code, self::RTL, true);
    }
}
