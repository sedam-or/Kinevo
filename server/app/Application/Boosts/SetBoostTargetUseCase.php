<?php

namespace App\Application\Boosts;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Boosts\BoostTarget;
use App\Domain\Boosts\Contracts\BoostTargetRepository;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Save a holiday Boost target (FR-37). Requires a confirmed Break Mode period
 * (Preconditions). The validity period is scoped by start/end datetime (FR-38
 * Business Rules) and must fall inside the active break. A proposed target above
 * the 70% safety cap is capped with an explicit warning (FR-37 Exception Flow).
 * Saving ends any previous active target (one active boost target at a time).
 */
final readonly class SetBoostTargetUseCase
{
    public function __construct(
        private BreakPeriodRepository $breaks,
        private BoostTargetRepository $boostTargets,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(
        int $userId,
        ?int $breakPeriodId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $targetPercent,
    ): SetBoostTargetResult {
        if ($targetPercent < 1 || $targetPercent > 100) {
            throw new InvalidArgumentException('Boost target percent must be between 1 and 100.');
        }

        $activeBreak = $this->breaks->findActiveForUser($userId);
        if ($activeBreak === null) {
            throw new InvalidArgumentException('Break Mode is not active; confirm a break before setting a boost target.');
        }

        if ($breakPeriodId !== null && $breakPeriodId !== $activeBreak->id) {
            throw new InvalidArgumentException('Boost target break_period_id does not match the active break.');
        }

        $start = $startDate->startOfDay();
        $end = $endDate->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Boost target end_date cannot precede start_date.');
        }

        if ($start->lt($activeBreak->startDate->startOfDay()) || $end->gt($activeBreak->endDate->endOfDay())) {
            throw new InvalidArgumentException('Boost target validity period must fall within the active break period.');
        }

        [$percent, $capped, $warning] = $this->enforceSafetyCap($targetPercent);

        $existing = $this->boostTargets->findActiveForUser($userId);
        if ($existing !== null) {
            $this->boostTargets->end($existing);
        }

        $saved = $this->boostTargets->create(BoostTarget::create(
            $userId,
            $activeBreak->id,
            $start,
            $end,
            $percent,
        ));

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::boostStart(),
            'boost_target',
            $saved->id,
            'Boost target set',
            payload: [
                'start_date' => $saved->startDate->toDateString(),
                'end_date' => $saved->endDate->toDateString(),
                'target_percent' => $saved->targetPercent,
                'capped' => $capped,
            ],
        ));

        $explanation = sprintf(
            'Boost target set to %d%% of capacity for %s to %s.',
            $saved->targetPercent,
            $saved->startDate->toDateString(),
            $saved->endDate->toDateString(),
        );

        if ($capped) {
            $explanation = "{$explanation} {$warning}";
        }

        return new SetBoostTargetResult($saved, $capped, $warning, $explanation);
    }

    /**
     * @return array{int, bool, ?string}
     */
    private function enforceSafetyCap(int $targetPercent): array
    {
        if (! BoostTarget::exceedsSafetyCap($targetPercent)) {
            return [$targetPercent, false, null];
        }

        $warning = sprintf(
            'The proposed %d%% exceeds the %d%% safety cap; the target was capped.',
            $targetPercent,
            BoostTarget::SAFETY_CAP_PERCENT,
        );

        return [BoostTarget::SAFETY_CAP_PERCENT, true, $warning];
    }
}
