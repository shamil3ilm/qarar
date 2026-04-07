<?php

return [
    'enabled' => env('ZATCA_INTEGRATION_ENABLED', true),
    'url' => env('ZATCA_INTEGRATION_URL', 'http://localhost:8001/api/v1'),
    'api_key' => env('ZATCA_INTEGRATION_API_KEY', ''),
    'webhook_secret' => env('ZATCA_INTEGRATION_WEBHOOK_SECRET', ''),
    'timeout' => (int) env('ZATCA_INTEGRATION_TIMEOUT', 30),
    'retry' => [
        'times' => (int) env('ZATCA_RETRY_TIMES', 3),
        'sleep' => (int) env('ZATCA_RETRY_SLEEP', 1000),
    ],
];
