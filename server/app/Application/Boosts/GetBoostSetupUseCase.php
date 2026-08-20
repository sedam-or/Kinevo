<?php

namespace App\Application\Boosts;

use App\Application\Adaptive\GetBurnoutSignalUseCase;
use App\Domain\Boosts\BoostTarget;
use App\Domain\Boosts\Contracts\BoostTargetRepository;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Scheduling\CapacityCalculator;
use App\Domain\Scheduling\EffectiveCapacity;
use App\Domain\Scheduling\WeekCapacitySample;
use Carbon\CarbonImmutable;

/**
 * Boost Mode setup (FR-37 Normal Flow: show current targets → compute
 * recommendations). Requires a confirmed Break Mode period (FR-37 Preconditions).
 * The recommendation reuses the Capacity feedback loop (FR-49): a recent average
 * realization above 90% with no burnout signal offers Boost Mode; the suggested
 * target is capped at the 70% safety limit.
 */
final readonly class GetBoostSetupUseCase
{
    public function __construct(
        private BreakPeriodRepository $breaks,
        private BoostTargetRepository $boostTargets,
        private WeekCapacitySampleProvider $samples,
        private GetBurnoutSignalUseCase $burnoutSignal,
        private CapacityCalculator $calculator,
    ) {}

    public function __invoke(int $userId): BoostSetupResult
    {
        $activeBreak = $this->breaks->findActiveForUser($userId);

        if ($activeBreak === null) {
            return new BoostSetupResult(
                eligible: false,
                activeTarget: null,
                recommendation: new BoostRecommendation(
                    eligible: false,
                    recommendation: BoostRecommendation::NOT_ELIGIBLE,
                    reason: 'Break Mode is not active; confirm a break before setting a boost target.',
                    averageRealization: 0.0,
                    recommendedTargetPercent: 0,
                ),
                safetyCapPercent: BoostTarget::SAFETY_CAP_PERCENT,
                breakPeriodId: null,
                breakStartDate: null,
                breakEndDate: null,
            );
        }

        $activeTarget = $this->boostTargets->findActiveForUser($userId);
        $burnout = ($this->burnoutSignal)($userId);
        $samples = $this->samples->forUser($userId, CarbonImmutable::now());
        $effective = $this->calculator->estimate($samples, $this->baselineTargetMinutes($samples), $burnout->active);

        return new BoostSetupResult(
            eligible: true,
            activeTarget: $activeTarget,
            recommendation: $this->recommendation($effective, $burnout->active),
            safetyCapPercent: BoostTarget::SAFETY_CAP_PERCENT,
            breakPeriodId: $activeBreak->id,
            breakStartDate: $activeBreak->startDate->toDateString(),
            breakEndDate: $activeBreak->endDate->toDateString(),
        );
    }

    private function recommendation(EffectiveCapacity $effective, bool $burnoutActive): BoostRecommendation
    {
        if ($burnoutActive) {
            return new BoostRecommendation(
                eligible: false,
                recommendation: BoostRecommendation::SUPPRESSED,
                reason: 'A burnout signal is active; aggressive boost scheduling is suppressed (FR-49).',
                averageRealization: $effective->realizationRatio,
                recommendedTargetPercent: 0,
            );
        }

        if ($effective->recommendation === 'BOOST_AVAILABLE') {
            $recommended = min(BoostTarget::SAFETY_CAP_PERCENT, (int) floor($effective->realizationRatio * 100));

            return new BoostRecommendation(
                eligible: true,
                recommendation: BoostRecommendation::BOOST_AVAILABLE,
                reason: $effective->reason,
                averageRealization: $effective->realizationRatio,
                recommendedTargetPercent: $recommended,
            );
        }

        return new BoostRecommendation(
            eligible: false,
            recommendation: $effective->recommendation,
            reason: $effective->reason,
            averageRealization: $effective->realizationRatio,
            recommendedTargetPercent: 0,
        );
    }

    /**
     * Baseline weekly productive target from the recent samples; falls back to a
     * neutral baseline when no reliable history exists.
     *
     * @param  array<int, WeekCapacitySample>  $samples
     */
    private function baselineTargetMinutes(array $samples): int
    {
        if ($samples === []) {
            return 3000;
        }

        return (int) floor(array_sum(array_map(
            static fn (WeekCapacitySample $s) => $s->plannedMinutes->value(),
            $samples,
        )) / count($samples));
    }
}
