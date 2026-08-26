<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\AiRuntimeLimitException;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\ValueObjects\AiResponse;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * TASK-P25-003..005/007/008 — AI request guard: metered preflight before any
 * provider call, postflight accounting on success, daily runtime safeguards
 * (P25-007), and the Kinevo-hosted vs BYOK ledger split (P25-008).
 *
 * - Kinevo-hosted (byok=false): spends one ai_credit, costs the run against
 *   the price catalog, ledger `kinevo` — Kinevo bears the inference cost.
 * - BYOK (byok=true): spends nothing and stores no Kinevo cost (ledger
 *   `byok`) — the user bears their provider's spend. Runtime safeguards apply
 *   to BOTH (no abuse bypass). Called from inference use cases, not
 *   controllers; CLI diagnostics (ai:smoke) bypass entirely.
 */
final readonly class AiCreditGuard
{
    public function __construct(
        private EntitlementService $entitlements,
        private AiRunRepository $runs,
        private AiCostEstimator $costEstimator,
    ) {}

    /**
     * Preflight before any provider call: economic layer (monthly ai_credits,
     * 403; skipped for BYOK) then runtime safety layer (TASK-P25-007, 429).
     * Issues the per-request identity for the run on success.
     */
    public function begin(int $userId, bool $byok = false): string
    {
        if (! $byok && $this->entitlements->remaining($userId, 'ai_credits') <= 0) {
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

    /**
     * Postflight accounting: consume + estimate + record the successful run
     * with the correct billing ledger. BYOK runs spend nothing, store no
     * Kinevo cost, and are marked ledger `byok`.
     */
    public function recordSuccess(
        int $userId,
        bool $byok,
        string $requestId,
        AiResponse $response,
        string $proposalType,
        ?int $schemaVersion,
        string $contextHash,
    ): void {
        $creditsConsumed = 0;
        $cost = ['estimated_cost_minor' => null, 'cost_currency' => null, 'pricing_source' => 'unpriced', 'pricing_snapshot_id' => null];
        $ledger = 'byok';

        if (! $byok) {
            $this->entitlements->consume($userId, 'ai_credits');
            $creditsConsumed = 1;
            $cost = $this->costEstimator->estimate(
                $response->provider,
                $response->model,
                $response->promptTokens,
                $response->completionTokens,
            );
            $ledger = 'kinevo';
        }

        $this->runs->record(AiRun::success(
            $userId,
            $response->provider,
            $response->model,
            $proposalType,
            $schemaVersion,
            null,
            $contextHash,
            $response->promptTokens,
            $response->completionTokens,
            $response->latencyMs,
            null,
            $creditsConsumed,
            $requestId,
            $cost['estimated_cost_minor'],
            $cost['cost_currency'],
            $cost['pricing_source'],
            $cost['pricing_snapshot_id'],
            $ledger,
        ));
    }
}
