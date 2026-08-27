<?php

namespace App\Application\Ai;

use App\Application\Saas\EntitlementService;
use App\Domain\Ai\AiRuntimeLimitException;
use App\Domain\Ai\BillingLedger;
use App\Domain\Ai\Contracts\AiRunRepository;
use App\Domain\Ai\Entities\AiRun;
use App\Domain\Ai\ValueObjects\AiResponse;
use App\Domain\Saas\Exceptions\EntitlementLimitException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * TASK-P25-003..005/007/008 + COMMERCIAL PRICING DELTA D-005 — AI request
 * guard: metered preflight before any provider call, postflight accounting on
 * success, daily runtime safeguards (P25-007), the per-request estimated
 * budget (RESERVE layer), and the Kinevo-hosted vs BYOK ledger split
 * (P25-008, biLLing-source semantics via BillingLedger).
 *
 * Reserve -> settle semantics (revisi-finance §10):
 *  - RESERVE: preflight checks allowance + daily caps + an upper-bound
 *    estimated cost for the configured model at the max token guards
 *    (`AI_REQUEST_BUDGET`); a request predicted beyond that bound is refused
 *    BEFORE any provider call.
 *  - SETTLE: on success the actual credits consumed and the ACTUAL token cost
 *    from the price catalog are recorded; on failure `credits_consumed = 0`
 *    (the reservation is released, nothing is burned).
 *
 * - Kinevo-hosted (byok=false): spends one ai_credit, costs the run against
 *   the price catalog, ledger INCLUDED_HOSTED (`kinevo`) — Kinevo bears the
 *   inference cost.
 * - BYOK (byok=true): spends nothing and stores no Kinevo cost (ledger `byok`)
 *   — the user bears their provider's spend. Runtime safeguards apply to BOTH
 *   (no abuse bypass). P25-010 cost alerts evaluated after every metered
 *   success. Called from inference use cases, not controllers; CLI diagnostics
 *   (ai:smoke) bypass entirely.
 */
final readonly class AiCreditGuard
{
    public function __construct(
        private EntitlementService $entitlements,
        private AiRunRepository $runs,
        private AiCostEstimator $costEstimator,
        private AiCostAlertService $alerts,
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

        $this->assertRequestBudget();
    }

    /**
     * COMMERCIAL PRICING DELTA D-005 — per-request estimated budget (RESERVE).
     * Estimates the configured provider/model worst-case cost at the max token
     * guards; refuses the request before any provider call when that reservation
     * would exceed the cap. No catalog price or no token guards => gate skipped.
     */
    private function assertRequestBudget(): void
    {
        $cap = (int) (config('ai.limits.max_request_budget_minor') ?: 0);
        $driver = (string) config('ai.driver');
        if ($cap <= 0 || $driver === '' || $driver === 'disabled') {
            return;
        }

        $model = (string) config("ai.{$driver}.model");
        if ($model === '') {
            return;
        }

        $maxIn = (int) (config('ai.limits.max_input_tokens') ?: 0);
        $maxOut = (int) (config('ai.limits.max_output_tokens') ?: 0);
        if ($maxIn <= 0 && $maxOut <= 0) {
            return;
        }

        $worst = $this->costEstimator->estimate($driver, $model, $maxIn > 0 ? $maxIn : null, $maxOut > 0 ? $maxOut : null);
        if (($worst['estimated_cost_minor'] ?? null) === null) {
            return; // unpriced model — nothing to reserve against
        }

        if ((int) $worst['estimated_cost_minor'] >= $cap) {
            throw new AiRuntimeLimitException('Per-request estimated cost would exceed the configured budget.', 'AI_REQUEST_BUDGET', [
                'limit' => $cap,
                'estimated_minor' => $worst['estimated_cost_minor'],
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
        $ledger = BillingLedger::BYOK;

        if (! $byok) {
            $this->entitlements->consume($userId, 'ai_credits');
            $creditsConsumed = 1;
            $cost = $this->costEstimator->estimate(
                $response->provider,
                $response->model,
                $response->promptTokens,
                $response->completionTokens,
            );
            $ledger = BillingLedger::INCLUDED_HOSTED;
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

        $this->alerts->evaluatePostSuccess($userId, $byok, $cost['estimated_cost_minor']);
    }
}
