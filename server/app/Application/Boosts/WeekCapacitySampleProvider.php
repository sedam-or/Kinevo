<?php

namespace App\Application\Boosts;

use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ValueObjects\DurationMinutes;
use App\Domain\Scheduling\WeekCapacitySample;
use Carbon\CarbonImmutable;

/**
 * Builds the recent week capacity samples (2-4 weeks) used by the Boost Mode
 * recommendation (FR-37). Reuses the existing repositories for planned minutes
 * (scheduled assignments) and completed minutes (focus sessions); weeks with no
 * reliable realization data, and weeks tagged by an Emergency Pause or an
 * active Break, are excluded from the feedback loop (FR-49 Exception Flows).
 */
final readonly class WeekCapacitySampleProvider
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private FocusSessionRepository $focusSessions,
        private BreakPeriodRepository $breaks,
        private PauseEventRepository $pauses,
    ) {}

    /**
     * @return array<int, WeekCapacitySample>
     */
    public function forUser(int $userId, CarbonImmutable $reference, int $windowWeeks = 4): array
    {
        $samples = [];

        for ($i = $windowWeeks; $i >= 1; $i--) {
            $weekStart = $reference->subWeeks($i)->startOfWeek();
            $weekEnd = $weekStart->addDays(6)->endOfDay();

            $planned = array_sum(array_map(
                static fn ($assignment) => $assignment->durationMinutes,
                $this->assignments->listForUserInRange($userId, $weekStart, $weekEnd),
            ));
            $completed = $this->focusSessions->sumDurationMinutesBetween($userId, $weekStart, $weekEnd);

            if ($planned < 1 || $completed < 1) {
                continue;
            }

            $samples[] = new WeekCapacitySample(
                new DurationMinutes($planned),
                new DurationMinutes($completed),
                $this->tag($userId, $weekStart),
            );
        }

        return $samples;
    }

    private function tag(int $userId, CarbonImmutable $weekStart): string
    {
        if ($this->breaks->coversWeek($userId, $weekStart)) {
            return 'break';
        }

        if ($this->pauses->isWeekExceptional($userId, $weekStart)) {
            return 'emergency';
        }

        return 'normal';
    }
}
