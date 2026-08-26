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
            'subscription' => [
                'state' => $subscription->state,
                'provider' => $subscription->provider,
                'cancel_at_period_end' => false, // P24 billing adds real semantics
            ],
            'usage' => $usage,
        ];
    }
}
