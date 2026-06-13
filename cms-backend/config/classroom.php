<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SendGrid Configuration
    |--------------------------------------------------------------------------
    |
    | Configure SendGrid for email notifications
    |
    */

    'sendgrid' => [
        'secret' => env('SENDGRID_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code Configuration
    |--------------------------------------------------------------------------
    |
    | Configure QR code generation settings
    |
    */

    'qr_code' => [
        'size' => 200,
        'margin' => 2,
        'format' => 'png',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Configure notification behavior
    |
    */

    'notifications' => [
        'email_enabled' => env('NOTIFICATION_EMAIL_ENABLED', true),
        'default_channel' => env('NOTIFICATION_DEFAULT_CHANNEL', 'email'),
    ],
];
