<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the rate limiters that will be applied to your
    | application. You can configure different limiters for different routes
    | and user types.
    |
    */

    'api' => [
        'limit' => env('API_RATE_LIMIT', 60),
        'decay_minutes' => env('API_RATE_LIMIT_DECAY', 1),
    ],

    'auth' => [
        'limit' => env('AUTH_RATE_LIMIT', 5),
        'decay_minutes' => env('AUTH_RATE_LIMIT_DECAY', 1),
    ],

    'uploads' => [
        'limit' => env('UPLOAD_RATE_LIMIT', 10),
        'decay_minutes' => env('UPLOAD_RATE_LIMIT_DECAY', 1),
    ],

    'sensitive' => [
        'limit' => env('SENSITIVE_RATE_LIMIT', 3),
        'decay_minutes' => env('SENSITIVE_RATE_LIMIT_DECAY', 5),
    ],
];
