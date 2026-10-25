<?php

// The super-admin console and the sign-in safety rules.
return [
    // The console lives under this path instead of a guessable /admin.
    // Set PLATFORM_PATH in .env to something only the provider knows.
    'path' => trim((string) env('PLATFORM_PATH', 'ctl'), '/') ?: 'ctl',

    // Normally empty: the console setting decides whether the sign-in picture
    // is shown every time (the default). A test machine may set
    // CAPTCHA_MODE=after_failures so automated sign-ins do not read pictures.
    'captcha' => env('CAPTCHA_MODE'),

    // The lock ladder (website guideline §4): after this many wrong passwords
    // a login is locked for 15 minutes, the next time for an hour, and the
    // third time until it is unlocked by hand.
    'lockout' => [
        'attempts' => (int) env('LOCKOUT_ATTEMPTS', 5),
        'first_minutes' => 15,
        'second_minutes' => 60,
    ],

    // Free trial length for a new company. The super-admin changes it in the
    // console (decision D20); this is the starting value.
    'trial_days' => (int) env('TRIAL_DAYS', 14),
];
