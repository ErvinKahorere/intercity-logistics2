<?php

return [
    'default' => env('SMS_PROVIDER', 'log'),
    'queue' => env('SMS_QUEUE', 'sms'),
    'cooldown_seconds' => (int) env('SMS_COOLDOWN_SECONDS', 120),
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '264'),
    'sender_id' => env('SMS_SENDER_ID', env('APP_NAME', 'InterCity Logistics')),
    'log_channel' => env('SMS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
    'providers' => [
        'log' => [
            'channel' => env('SMS_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        ],
        'twilio' => [
            'enabled' => filter_var(env('TWILIO_SMS_ENABLED', false), FILTER_VALIDATE_BOOL),
            'sid' => env('TWILIO_ACCOUNT_SID', env('TWILIO_SID')),
            'token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_SMS_FROM', env('TWILIO_FROM')),
            'messaging_service_sid' => env('TWILIO_MESSAGING_SERVICE_SID'),
            'status_callback' => env('TWILIO_STATUS_CALLBACK'),
            'validate_signature' => filter_var(env('TWILIO_VALIDATE_SIGNATURE', true), FILTER_VALIDATE_BOOL),
        ],
    ],
    'preferences' => [
        'driver_assigned' => true,
        'job_accepted' => true,
        'parcel_picked_up' => true,
        'parcel_in_transit' => true,
        'parcel_delivered' => true,
        'driver_match' => true,
        'urgent_job_alert' => true,
        'verification_updates' => true,
        'important_alerts' => true,
        'billing_updates' => true,
    ],
];
