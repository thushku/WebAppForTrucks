<?php

/*
 * Only the values this product changes. Everything else comes from the
 * framework's own defaults (Laravel merges them in).
 */
return [
    // The product name. The super-admin can change it under Branding
    // (decision D20); this is only the starting value and the fallback.
    'name' => env('APP_NAME', 'Rhydo Fleet'),

    // Shown in the side menu and the console footer (the web guideline's
    // "visible versioning"), and written into exported template files.
    'version' => env('APP_VERSION', '0.1.0'),
    'build' => env('APP_BUILD', '1'),

    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),

    // Stored times are UTC; each company shows its own time zone.
    'timezone' => 'UTC',

    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'en_IN'),

    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],
];
