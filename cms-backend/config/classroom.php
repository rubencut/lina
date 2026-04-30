<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Twilio Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Twilio SMS service for sending SMS notifications
    |
    */

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'phone_from' => env('TWILIO_PHONE_FROM'),
    ],

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
        'sms_enabled' => env('NOTIFICATION_SMS_ENABLED', false),
        'default_channel' => env('NOTIFICATION_DEFAULT_CHANNEL', 'email'),
    ],
];
