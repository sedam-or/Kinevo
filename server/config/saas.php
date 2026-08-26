<?php

/**
 * TASK-P23-002/003 — SaaS plan catalogue. Product data lives HERE (config),
 * never hardcoded as `if plan == 'pro'` in code. Enforcement goes through
 * App\Application\Saas\EntitlementService.
 *
 * LOCKED BUSINESS DECISIONS (owner, 2026-08-26):
 *  - Tiers are exactly Free / Pro / Power (the former `personal` tier is
 *    retired; active legacy rows degrade to the default plan via
 *    Subscription::effectivePlanCode()).
 *  - Monthly prices live in config/billing.php: Pro = IDR 34,900, Power =
 *    IDR 49,900. Annual billing is architecturally supported but NO annual
 *    price/discount exists until an explicit owner decision.
 *  - BYOK (`custom_provider`) is FALSE on Free, TRUE on Pro/Power. BYOK never
 *    consumes Kinevo-hosted ai_credits but stays bound by runtime safeguards
 *    (P25-007).
 *
 * Entitlement keys:
 *  - max_workspaces  (int limit)
 *  - ai_credits      (int monthly allowance)
 *  - export          (bool: activity/ics export)
 *  - advanced_analytics / wrapped / mobile_access (reserved; enforced by P28+)
 *  - custom_provider (bool: BYOK, enforced since P25-008)
 */
return [
    'default_plan' => 'free',

    'plans' => [
        'free' => [
            'name' => 'Free',
            'entitlements' => [
                'max_workspaces' => 2,
                'ai_credits' => 20,
                'export' => false,
                'advanced_analytics' => false,
                'wrapped' => false,
                'mobile_access' => true,
                'custom_provider' => false,
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'entitlements' => [
                'max_workspaces' => 10,
                'ai_credits' => 300,
                'export' => true,
                'advanced_analytics' => true,
                'wrapped' => false,
                'mobile_access' => true,
                'custom_provider' => true,
            ],
        ],
        'power' => [
            'name' => 'Power',
            'entitlements' => [
                'max_workspaces' => 25,
                'ai_credits' => 1000,
                'export' => true,
                'advanced_analytics' => true,
                'wrapped' => true,
                'mobile_access' => true,
                'custom_provider' => true,
            ],
        ],
    ],
];
