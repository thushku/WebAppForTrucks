<?php

use App\Models\Account;
use App\Platform\Console\Admin;

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => 'accounts',
    ],

    'guards' => [
        // Office users and crew: one login identity per real person (an
        // "account"), which may belong to several companies (decision P7).
        'web' => [
            'driver' => 'session',
            'provider' => 'accounts',
        ],
        // The super-admin console: a separate sign-in with a mandatory
        // email code, on its own table.
        'platform' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    'providers' => [
        'accounts' => [
            'driver' => 'eloquent',
            'model' => Account::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
    ],

    // Password reset links are our own one-time tokens (PasswordToken), not
    // Laravel's broker; this entry only keeps framework defaults quiet.
    'passwords' => [
        'accounts' => [
            'provider' => 'accounts',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
