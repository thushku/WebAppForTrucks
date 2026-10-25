<?php

return [
    // Push to the phone apps (M20, decision D18): fcm, log (default while no Firebase project is set) or none.
    // FIREBASE_CREDENTIALS is the path of the service-account JSON — kept outside the repository, never committed.
    'fcm' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'android_channel' => env('FIREBASE_ANDROID_CHANNEL', 'fleet'),
    ],

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
