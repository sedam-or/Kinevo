<?php

/**
 * P27 — NativePHP Android shell config. Mobile-only values; web deploys never
 * touch them (env() is read inside config only, so config:cached stays safe).
 */
return [
    'api_base' => env('KINEVO_API_BASE', 'http://10.0.2.2:8000/api/v1'),
    'app_version' => env('NATIVEPHP_APP_VERSION', '0.1.0'),
    'dev_email' => env('KINEVO_DEV_EMAIL', 'test@example.com'),
    'dev_password' => env('KINEVO_DEV_PASSWORD', 'password'),
];
