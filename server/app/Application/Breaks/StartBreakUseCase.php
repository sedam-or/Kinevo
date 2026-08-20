<?php

namespace App\Application\Breaks;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Breaks\BreakPeriod;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Confirm a Break Mode period (FR-36 manual flow). The user picks a manual date
 * range; detection never activates a break without confirmation, so the period
 * is persisted only after this explicit confirmation. The week(s) covered are
 * tagged exceptional for capacity feedback (FR-49). One active break at a time.
 */
final readonly class StartBreakUseCase
{
    public function __construct(
        private BreakPeriodRepository $breaks,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $startDate, CarbonImmutable $endDate): StartBreakResult
    {
        $start = $startDate->startOfDay();
        $end = $endDate->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('Break period end_date cannot precede start_date.');
        }

        $existing = $this->breaks->findActiveForUser($userId);
        if ($existing !== null) {
            throw new InvalidArgumentException(
                'An active break already exists ('.implode(' to ', [
                    $existing->startDate->toDateString(),
                    $existing->endDate->toDateString(),
                ]).'). End it before starting another.',
            );
        }

        $period = $this->breaks->create(BreakPeriod::create($userId, $start, $end));

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::breakStart(),
            'break',
            $period->id,
            'Break Mode started',
            payload: [
                'start_date' => $period->startDate->toDateString(),
                'end_date' => $period->endDate->toDateString(),
                'duration_days' => $period->durationDays(),
            ],
        ));

        return new StartBreakResult(
            $period->id,
            $period->startDate->toDateString(),
            $period->endDate->toDateString(),
            sprintf(
                'Break Mode confirmed for %s to %s (%d days). Notifications are suppressed and the covered weeks are excluded from capacity estimates.',
                $period->startDate->toDateString(),
                $period->endDate->toDateString(),
                $period->durationDays(),
            ),
        );
    }
}
