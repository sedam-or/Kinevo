<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\AiRuntimeLimitException;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * TASK-P25-003..005/007 — AI request guard: metered preflight before any
 * provider call and postflight consumption on success, PLUS daily runtime
 * safeguards (request count / estimated cost — P25-007). Called from the
 * inference use cases (not controllers) so every entry point enforces the
 * same gates; CLI diagnostics opt out. One credit is spent per successful
 * Kinevo-funded inference; failures and denials burn nothing. BYOK requests
 * (P25-008) skip the credit spend but keep the runtime safeguards.
 */
final readonly class AiCreditGuard
{
    public function __construct(
        private EntitlementService $entitlements,
        private AiRunRepository $runs,
    ) {}

    /**
     * Preflight before any provider call: the economic layer (monthly
     * ai_credits, 403) then the runtime safety layer (TASK-P25-007, 429).
     * Issues the per-request identity for the run on success.
     */
    public function begin(int $userId): string
    {
        if ($this->entitlements->remaining($userId, 'ai_credits') <= 0) {
            $plan = $this->entitlements->planFor($userId);

            throw new EntitlementLimitException(
                "Monthly AI credits exhausted on the {$plan->name} plan.",
                'ai_credits',
                $plan->code,
                [
                    'limit' => 0,
                    'used' => $this->entitlements->used($userId, 'ai_credits'),
                ],
            );
        }

        $this->assertDailyLimits($userId);

        return (string) Str::uuid();
    }

    /**
     * TASK-P25-007 — daily runtime safeguards (requests and estimated cost).
     * Config-driven (null = no cap); applied to hosted AND BYOK requests.
     */
    private function assertDailyLimits(int $userId): void
    {
        $dayStart = CarbonImmutable::now()->startOfDay();

        $maxDay = (int) (config('ai.limits.max_requests_per_day') ?: 0);
        if ($maxDay > 0 && $this->runs->countSince($userId, $dayStart) >= $maxDay) {
            throw new AiRuntimeLimitException('Daily AI request limit reached.', 'AI_DAILY_LIMIT', [
                'limit' => $maxDay,
            ]);
        }

        $maxCost = (int) (config('ai.limits.max_estimated_daily_cost_minor') ?: 0);
        if ($maxCost > 0 && $this->runs->sumEstimatedCostSince($userId, $dayStart) >= $maxCost) {
            throw new AiRuntimeLimitException('Daily AI estimated cost limit reached.', 'AI_DAILY_COST_LIMIT', [
                'limit' => $maxCost,
            ]);
        }
    }

    /** Postflight: spend one credit for a successful generation. */
    public function spend(int $userId): void
    {
        $this->entitlements->consume($userId, 'ai_credits');
    }
}
