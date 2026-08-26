<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\Contracts\AiCostAlertRepository;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiCostAlert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * TASK-P25-010 — AI usage/cost alert events, evaluated post-success.
 *
 * User-facing events (in-app unread until seen):
 *  - user.usage_threshold : monthly ai_credits crossed thresholds (50/75/90/100).
 * Ops-facing events (logged + stored with user_id NULL; never shown to users):
 *  - ops.daily_cost       : company-wide estimated Kinevo spend today ≥ cap.
 *  - ops.user_anomaly     : a user exceeded daily request rate ≥ cap.
 *
 * Channels (email/Slack/Telegram/notification center) are deliberately NOT
 * built here; this service only records domain events so the in-app surface
 * (P25-009) and ops logs can consume them later.
 */
final readonly class AiCostAlertService
{
    public function __construct(
        private EntitlementService $entitlements,
        private AiRunRepository $runs,
        private AiCostAlertRepository $alerts,
    ) {}

    /**
     * @param  int|null  $estimatedCostMinor  Kinevo-hosted estimated cost in
     *                                        minor units (null = BYOK).
     */
    public function evaluatePostSuccess(int $userId, bool $byok, ?int $estimatedCostMinor): void
    {
        $this->maybeUserAnomaly($userId);

        if ($byok) {
            return;
        }

        $this->maybeUsageThreshold($userId);
        $this->maybeOpsDailyCost($estimatedCostMinor);
    }

    private function maybeUsageThreshold(int $userId): void
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $limit = $this->entitlements->limit($userId, 'ai_credits');
        if ($limit <= 0) {
            return;
        }

        $used = $this->entitlements->used($userId, 'ai_credits');
        $percent = $used > 0 ? round(($used * 100) / $limit, 1) : 0;

        foreach ($this->usageThresholds() as $threshold) {
            if ($percent < $threshold) {
                continue;
            }
            if ($this->alerts->existsSince(AiCostAlert::KIND_USER_USAGE_THRESHOLD, $userId, $monthStart, $threshold)) {
                continue;
            }

            $this->alerts->create(AiCostAlert::userUsageThreshold(
                $userId,
                $threshold,
                ['used' => $used, 'limit' => $limit, 'percent' => $percent],
            ));
        }
    }

    private function maybeOpsDailyCost(?int $estimatedCostMinor): void
    {
        $threshold = $this->opsThreshold('ops_daily_cost_minor');
        if ($threshold <= 0) {
            return;
        }

        $dayStart = CarbonImmutable::now()->startOfDay();
        if ($this->alerts->existsSince(AiCostAlert::KIND_OPS_DAILY_COST, null, $dayStart)) {
            return;
        }

        $total = $this->runs->sumEstimatedCostForAllSince($dayStart);
        if ($total < $threshold) {
            return;
        }

        $alert = $this->alerts->create(AiCostAlert::ops(
            AiCostAlert::KIND_OPS_DAILY_COST,
            $threshold,
            ['estimated_cost_minor' => $total],
        ));

        Log::warning('ai.ops.daily_cost_threshold', [
            'alert_id' => $alert->id,
            'estimated_cost_minor' => $total,
            'threshold_minor' => $threshold,
        ]);
    }

    private function maybeUserAnomaly(int $userId): void
    {
        $threshold = $this->opsThreshold('user_anomaly_daily_requests');
        if ($threshold <= 0) {
            return;
        }

        $dayStart = CarbonImmutable::now()->startOfDay();
        if ($this->alerts->existsSince(AiCostAlert::KIND_OPS_USER_ANOMALY, $userId, $dayStart)) {
            return;
        }

        $count = $this->runs->countSince($userId, $dayStart);
        if ($count < $threshold) {
            return;
        }

        $alert = $this->alerts->create(AiCostAlert::ops(
            AiCostAlert::KIND_OPS_USER_ANOMALY,
            $threshold,
            ['requests' => $count],
            $userId,
        ));

        Log::warning('ai.ops.user_anomaly', [
            'alert_id' => $alert->id,
            'user_id' => $userId,
            'requests' => $count,
        ]);
    }

    /** @return array<int, int> */
    private function usageThresholds(): array
    {
        $thresholds = (array) (config('ai.alerts.usage_thresholds') ?: []);

        return array_values(array_unique(array_map('intval', $thresholds)));
    }

    private function opsThreshold(string $key): int
    {
        $raw = config("ai.alerts.$key");

        return $raw === null ? 0 : (int) $raw;
    }
}
