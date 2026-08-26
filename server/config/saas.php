<?php

/**
 * TASK-P23-002/003 — SaaS plan catalogue. Product data lives HERE (config),
 * never hardcoded as `if plan == 'pro'` in code. Enforcement goes through
 * App\Application\Saas\EntitlementService.
 *
 * Entitlement keys:
 *  - max_workspaces  (int limit)
 *  - ai_credits      (int monthly allowance)
 *  - export          (bool: activity/ics export)
 * Reserved-but-not-yet-enforced keys (require approved product requirements
 * before enforcement lands): advanced_analytics, wrapped, mobile_access,
 * custom_provider. custom_provider stays TRUE on every plan for now — it is
 * existing core behaviour (P18) and gating it would mutilate the product.
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
                'custom_provider' => true,
            ],
        ],
        'personal' => [
            'name' => 'Personal',
            'entitlements' => [
                'max_workspaces' => 5,
                'ai_credits' => 100,
                'export' => true,
                'advanced_analytics' => false,
                'wrapped' => false,
                'mobile_access' => true,
                'custom_provider' => true,
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
