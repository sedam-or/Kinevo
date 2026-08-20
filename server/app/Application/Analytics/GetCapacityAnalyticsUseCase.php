<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\CapacityAnalytics;
use App\Application\Boosts\WeekCapacitySampleProvider;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Scheduling\CapacityCalculator;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;

/**
 * Capacity read model (TASK-130/TASK-132): per-day capacity indicators
 * (scheduled/available/overload), the realization ratio, the weekly trend, and
 * the Capacity feedback-loop estimate (FR-49). Reuses the existing scheduling
 * primitives — the algorithm is never recreated.
 */
final readonly class GetCapacityAnalyticsUseCase
{
    public const TARGET_CAPACITY_MINUTES = 1440;

    public function __construct(
        private WeekCapacitySampleProvider $samples,
        private CapacityCalculator $calculator,
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private FocusSessionRepository $focusSessions,
        private SlotCalculator $slots,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): CapacityAnalytics
    {
        $days = [];
        $scheduledTotal = 0;
        $cursor = $from->startOfDay();
        while ($cursor->lte($to->endOfDay())) {
            [$scheduled, $available] = $this->dayCapacity($userId, $cursor);
            $scheduledTotal += $scheduled;
            $overload = max(0, $scheduled - $available);

            $days[] = [
                'date' => $cursor->toDateString(),
                'scheduled_minutes' => $scheduled,
                'available_minutes' => $available,
                'overload_minutes' => $overload,
                'status' => $overload > 0 ? 'overload' : 'ok',
            ];

            $cursor = $cursor->addDay();
        }

        $focusTotal = $this->focusSessions->sumDurationMinutesBetween($userId, $from, $to);
        $realization = $scheduledTotal > 0 ? min(1.0, $focusTotal / $scheduledTotal) : 0.0;

        $byWeek = $this->samples->samplesByWeek($userId, $to);
        $weeks = [];
        foreach ($byWeek as $weekStart => $sample) {
            $weeks[] = [
                'week_start' => $weekStart,
                'planned_minutes' => $sample->plannedMinutes->value(),
                'completed_minutes' => $sample->completedMinutes->value(),
                'realization' => round($sample->realizationRatio(), 4),
                'tag' => $sample->tag,
            ];
        }

        $estimate = $this->calculator->estimate(array_values($byWeek), self::TARGET_CAPACITY_MINUTES);

        return new CapacityAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            $weeks,
            $days,
            round($realization, 4),
            round($estimate->realizationRatio, 4),
            $estimate->confidence,
            $estimate->recommendation,
            $estimate->reason,
            self::TARGET_CAPACITY_MINUTES,
        );
    }

    /**
     * Canonical day capacity (same primitives as the Today view): scheduled
     * minutes from non-cancelled assignments, available minutes from the empty
     * slots between occupied events and Hard Landscape.
     *
     * @return array{0: int, 1: int} scheduled, available
     */
    private function dayCapacity(int $userId, CarbonImmutable $date): array
    {
        $assignments = array_values(array_filter(
            $this->assignments->listForUserOnDate($userId, $date),
            static fn ($a) => ! $a->status->equals(ScheduleAssignmentStatus::cancelled()),
        ));

        $occupied = array_merge(
            array_map(static fn ($a) => $a->timeRange(), $assignments),
            array_map(static fn ($e) => $e->timeRange(), $this->hardLandscape->listForUserOnDate($userId, $date)),
        );

        $day = new TimeRange($date->startOfDay(), $date->endOfDay());
        $available = array_sum(array_map(
            static fn (TimeRange $slot) => $slot->durationMinutes()->value(),
            $this->slots->calculate($day, $occupied),
        ));

        $scheduled = array_sum(array_map(
            static fn ($a) => $a->durationMinutes,
            $assignments,
        ));

        return [$scheduled, $available];
    }
}
