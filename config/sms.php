<?php

declare(strict_types=1);

return [
    'driver' => env('SMS_DRIVER', 'log'),

    'vonage' => [
        'api_key'    => env('VONAGE_API_KEY', ''),
        'api_secret' => env('VONAGE_API_SECRET', ''),
        'from'       => env('VONAGE_SMS_FROM', 'ERP'),
    ],

    'twilio' => [
        'sid'   => env('TWILIO_SID', ''),
        'token' => env('TWILIO_AUTH_TOKEN', ''),
        'from'  => env('TWILIO_FROM', ''),
    ],
];
