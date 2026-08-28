<?php

namespace App\Application\Saas;

use App\Domain\Saas\Plan;

/**
 * TASK-P23-008 — safe plan overview for the settings UI: effective plan,
 * its entitlements, and current usage for metered keys. Never includes
 * provider secrets or billing internals.
 */
final readonly class GetPlanOverviewUseCase
{
    public function __construct(
        private EntitlementService $entitlements,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(int $userId): array
    {
        $subscription = $this->entitlements->subscriptionFor($userId);
        $plan = Plan::fromConfig($subscription->effectivePlanCode());

        $usage = [];
        foreach (['ai_credits'] as $metered) {
            $allowance = (int) ($plan->entitlement($metered) ?? 0);
            $used = $this->entitlements->used($userId, $metered);
            $usage[$metered] = [
                'allowance' => $allowance,
                'used' => min($used, $allowance),
                'remaining' => max(0, $allowance - $used),
                'period' => $this->entitlements->periodFor(),
            ];
        }

        return [
            'plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'entitlements' => $plan->entitlements,
            ],
            // Full tier catalogue (single source: config/saas.php) so the
            // pricing/upgrade UI renders every tier from data — no hardcoded
            // numbers in the frontend (COMMERCIAL PRICING DELTA).
            'catalog' => [
                'free' => ['name' => 'Free', 'entitlements' => Plan::fromConfig('free')->entitlements],
                'pro' => ['name' => 'Pro', 'entitlements' => Plan::fromConfig('pro')->entitlements],
                'power' => ['name' => 'Power', 'entitlements' => Plan::fromConfig('power')->entitlements],
            ],
            'subscription' => [
                'state' => $subscription->state,
                'provider' => $subscription->provider,
                'cancel_at_period_end' => false, // P24 billing adds real semantics
            ],
            'usage' => $usage,
            // COMMERCIAL PRICING DELTA (revisi-finance.md) — single price
            // source for the pricing/upgrade UI. Launch hypotheses; never
            // claim "final market price". Free is unpriced (IDR 0).
            'pricing' => [
                'free' => ['currency' => 'IDR', 'amount_major' => 0, 'amount_minor' => 0, 'interval' => 'MONTH', 'interval_count' => 1, 'launch_hypothesis' => true],
                'pro' => ['currency' => 'IDR', 'amount_major' => (int) config('billing.prices.pro.amount_major'), 'amount_minor' => (int) config('billing.prices.pro.amount_major') * 100, 'interval' => 'MONTH', 'interval_count' => 1, 'launch_hypothesis' => true],
                'power' => ['currency' => 'IDR', 'amount_major' => (int) config('billing.prices.power.amount_major'), 'amount_minor' => (int) config('billing.prices.power.amount_major') * 100, 'interval' => 'MONTH', 'interval_count' => 1, 'launch_hypothesis' => true],
            ],
        ];
    }
}
