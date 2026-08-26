<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\Contracts\AiRunRepository;
use Carbon\CarbonImmutable;

/**
 * TASK-P25-009 — AI Usage summary for the Settings surface. Summary-first per
 * owner brief: plan + ai_credits progress/reset, estimated Kinevo-hosted cost
 * this month, BYOK request count, per-feature breakdown, and unread alerts.
 * No charts (daily chart is explicitly deferred). Read-only.
 */
final readonly class GetAiUsageSummaryUseCase
{
    public function __construct(
        private EntitlementService $entitlements,
        private AiRunRepository $runs,
        private ListAiCostAlertsUseCase $alerts,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(int $userId): array
    {
        $period = $this->entitlements->periodFor();
        $periodStart = CarbonImmutable::createFromFormat('!Y-m', $period)
            ?? CarbonImmutable::now()->startOfMonth();
        $periodEnd = $periodStart->addMonth()->subSecond();

        $plan = $this->entitlements->planFor($userId);
        $limit = $this->entitlements->limit($userId, 'ai_credits');
        $used = $this->entitlements->used($userId, 'ai_credits');

        $usage = $this->runs->monthlyUsageForUser($userId, $periodStart);
        $unseen = $this->alerts->listUnseen($userId);

        return [
            'period' => $period,
            'period_start' => $periodStart->toISOString(),
            'period_end' => $periodEnd->toISOString(),
            'plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
            ],
            'credits' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
                'percent' => $limit > 0 ? round(($used * 100) / $limit, 1) : 0.0,
            ],
            'kinevo' => [
                'request_count' => $usage['kinevo_count'],
                'estimated_cost_minor' => $usage['kinevo_cost_minor'],
                'currency' => (string) (config('ai.cost.default_currency') ?: 'USD'),
            ],
            'byok' => [
                'request_count' => $usage['byok_count'],
            ],
            'breakdown' => $this->runs->monthlyBreakdown($userId, $periodStart),
            'alerts' => [
                'unread_count' => count($unseen),
                'items' => array_slice($unseen, 0, 5),
            ],
        ];
    }
}
