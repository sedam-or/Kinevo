<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Rate Limits
    |--------------------------------------------------------------------------
    |
    | Per-user per-minute safeguard for authenticated API traffic
    | (TASK-P22-006; see AppServiceProvider `RateLimiter::for('api', …)`).
    | Production baseline is 120 requests/minute. Sandbox / e2e environments may
    | raise it via API_MAX_REQUESTS_PER_MINUTE to accommodate browser suites
    | that sweep every surface in one run.
    |
    */
    'limits' => [
        'max_requests_per_minute' => env('API_MAX_REQUESTS_PER_MINUTE'),
        'auth_max_attempts_per_minute' => env('AUTH_MAX_ATTEMPTS_PER_MINUTE', 5),
    ],
];
