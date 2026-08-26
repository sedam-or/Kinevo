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
        // Sandbox-only saved card token (saved_token_id from a one-click Snap
        // charge). Production flow captures the token per user at checkout.
        'test_card_token' => env('MIDTRANS_TEST_CARD_TOKEN'),
        'base_url' => env('MIDTRANS_ENV', 'sandbox') === 'production'
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',
    ],

    // TASK-P24-005 — price rows per plan (one price per plan for now; the
    // schema supports multiple prices/platforms later without migration churn).
    // LOCKED (owner, 2026-08-26): Pro = IDR 34,900/mo, Power = IDR 49,900/mo,
    // Free = IDR 0 (no paid row). Annual billing is supported by this shape
    // (interval/interval_count) but NO annual price exists until an explicit
    // owner decision — do not invent one.
    'prices' => [
        'pro' => ['currency' => 'IDR', 'amount_minor' => 3_490_000, 'interval' => 'MONTH', 'interval_count' => 1],
        'power' => ['currency' => 'IDR', 'amount_minor' => 4_990_000, 'interval' => 'MONTH', 'interval_count' => 1],
    ],
];
