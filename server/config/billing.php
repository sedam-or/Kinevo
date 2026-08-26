<?php

/**
 * TASK-P24 — billing configuration. Prices are PRODUCT DATA (integer minor
 * units; IDR carried ×100 internally, exposed to Midtrans as whole rupiah).
 * Provider credentials come from environment — never committed values here.
 */
return [
    'midtrans' => [
        'env' => env('MIDTRANS_ENV', 'sandbox'),
        'webhook_verify' => env('MIDTRANS_WEBHOOK_VERIFY', true),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'base_url' => env('MIDTRANS_ENV', 'sandbox') === 'production'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
    ],

    // TASK-P24-005 — price rows per plan (one price per plan for now; the
    // schema supports multiple prices/platforms later without migration churn).
    'prices' => [
        'personal' => ['currency' => 'IDR', 'amount_minor' => 4_900_000, 'interval' => 'MONTH', 'interval_count' => 1],
        'pro' => ['currency' => 'IDR', 'amount_minor' => 9_900_000, 'interval' => 'MONTH', 'interval_count' => 1],
        'power' => ['currency' => 'IDR', 'amount_minor' => 19_900_000, 'interval' => 'MONTH', 'interval_count' => 1],
    ],
];
