<?php

return [
    'postmark' => ['key' => env('POSTMARK_API_KEY')],
    'resend' => ['key' => env('RESEND_API_KEY')],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // One-time codes by SMS / WhatsApp (decision D21). 'log' = development only.
    'otp_sms' => [
        'driver' => env('OTP_SMS_DRIVER', 'log'),
    ],

    // Headless Chromium for PDFs (blueprint 10 §10).
    'chrome' => [
        'path' => env('CHROME_PATH'),
    ],
];
