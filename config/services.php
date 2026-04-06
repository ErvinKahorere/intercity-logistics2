<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID', env('TWILIO_SID')),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_SMS_FROM', env('TWILIO_FROM')),
        'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
        'whatsapp_enabled' => filter_var(env('TWILIO_WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
        'whatsapp_status_callback' => env('TWILIO_WHATSAPP_STATUS_CALLBACK'),
    ],

];
