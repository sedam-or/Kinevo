<?php

namespace App\Application\Breaks;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use InvalidArgumentException;

/**
 * End the user's active Break Mode period (FR-36/FR-39). Produces the break
 * summary (start/end dates and duration) for the in-app report. A user with no
 * active break is a no-op.
 */
final readonly class EndBreakUseCase
{
    public function __construct(
        private BreakPeriodRepository $breaks,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId): EndBreakResult
    {
        $period = $this->breaks->findActiveForUser($userId);
        if ($period === null) {
            return new EndBreakResult(
                false,
                null,
                null,
                null,
                null,
                'No active Break Mode period to end.',
            );
        }

        $ended = $this->breaks->end($period);
        if ($ended === null) {
            throw new InvalidArgumentException('Break period could not be ended.');
        }

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::breakEnd(),
            'break',
            $ended->id,
            'Break Mode ended',
            payload: [
                'start_date' => $ended->startDate->toDateString(),
                'end_date' => $ended->endDate->toDateString(),
                'duration_days' => $ended->durationDays(),
            ],
        ));

        return new EndBreakResult(
            true,
            $ended->id,
            $ended->startDate->toDateString(),
            $ended->endDate->toDateString(),
            $ended->durationDays(),
            sprintf(
                'Break Mode ended. The break covered %s to %s (%d days); the covered weeks are no longer tagged exceptional.',
                $ended->startDate->toDateString(),
                $ended->endDate->toDateString(),
                $ended->durationDays(),
            ),
        );
    }
}
