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

    // TASK-P24-005 / COMMERCIAL PRICING DELTA (owner, 2026-08-28) — price rows
    // per plan. Price decision LOCKED as launch hypotheses (revisi-finance.md):
    // Pro = IDR 49,900/mo, Power = IDR 89,900/mo, Free = IDR 0 (no paid row).
    // Annual billing is supported by this shape (interval/interval_count) but
    // NO annual price exists until an explicit owner decision — do not invent one.
    'prices' => [
        'pro' => ['currency' => 'IDR', 'amount_minor' => 4_990_000, 'interval' => 'MONTH', 'interval_count' => 1],
        'power' => ['currency' => 'IDR', 'amount_minor' => 8_990_000, 'interval' => 'MONTH', 'interval_count' => 1],
    ],

    // COMMERCIAL PRICING DELTA D-006 — payment fee model per method
    // (revisi-finance §20). ASSUMPTIONS ONLY (verified=false): the merchant
    // contract (fixed + percentage per method) MUST replace these before any
    // production economics. Never assume one flat fee per user.
    'payment_fees' => [
        'credit_card' => ['fixed_minor' => 100, 'percentage_bps' => 290, 'verified' => false],
        'bank_transfer' => ['fixed_minor' => 150, 'percentage_bps' => 100, 'verified' => false],
        'qris' => ['fixed_minor' => 100, 'percentage_bps' => 70, 'verified' => false],
        'gopay' => ['fixed_minor' => 100, 'percentage_bps' => 220, 'verified' => false],
    ],
    'default_payment_method' => 'credit_card',

    // COMMERCIAL PRICING DELTA D-006 — unit economics planning grid
    // (revisi-finance §8/§18). COGS shares are the hosted-AI monthly cost as a
    // fraction of plan revenue per scenario. All DEPRECATED-BASELINE until
    // real usage/cost measurement (P32/P37); feeds `billing:unit-economics`.
    'unit_economics' => [
        'note' => 'DEPRECATED-BASELINE assumptions until measurement. AI COGS shares must be replaced by simulator/ops numbers as they become available.',
        'scenarios' => [
            'free' => ['expected_cogs_share' => 0.01, 'heavy_cogs_share' => 0.05, 'abuse_cogs_share' => 0.25],
            'pro' => ['p50_cogs_share' => 0.01, 'p95_cogs_share' => 0.03, 'p99_cogs_share' => 0.08, 'heavy_cogs_share' => 0.20],
            'power' => ['p50_cogs_share' => 0.01, 'p95_cogs_share' => 0.03, 'p99_cogs_share' => 0.08, 'heavy_cogs_share' => 0.20],
        ],
        'infra_minor_per_user_month' => (int) env('BILLING_INFRA_COST_MINOR', 25_000),
        'storage_minor_per_user_month' => (int) env('BILLING_STORAGE_COST_MINOR', 5_000),
        'support_minor_per_user_month' => (int) env('BILLING_SUPPORT_COST_MINOR', 10_000),
    ],
];
