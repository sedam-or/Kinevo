<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\CapacityAnalytics;
use App\Application\Boosts\WeekCapacitySampleProvider;
use App\Domain\Scheduling\CapacityCalculator;
use Carbon\CarbonImmutable;

/**
 * Capacity read model (TASK-130): the capacity feedback-loop snapshot for the
 * recent weeks (FR-49). Reuses WeekCapacitySampleProvider and CapacityCalculator
 * — the algorithm is never recreated here.
 */
final readonly class GetCapacityAnalyticsUseCase
{
    public const TARGET_CAPACITY_MINUTES = 1440;

    public function __construct(
        private WeekCapacitySampleProvider $samples,
        private CapacityCalculator $calculator,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $reference): CapacityAnalytics
    {
        $byWeek = $this->samples->samplesByWeek($userId, $reference);

        $rows = [];
        foreach ($byWeek as $weekStart => $sample) {
            $rows[] = [
                'week_start' => $weekStart,
                'planned_minutes' => $sample->plannedMinutes->value(),
                'completed_minutes' => $sample->completedMinutes->value(),
                'realization' => round($sample->realizationRatio(), 4),
                'tag' => $sample->tag,
            ];
        }

        $estimate = $this->calculator->estimate(array_values($byWeek), self::TARGET_CAPACITY_MINUTES);

        return new CapacityAnalytics(
            $rows,
            round($estimate->realizationRatio, 4),
            $estimate->confidence,
            $estimate->recommendation,
            $estimate->reason,
            self::TARGET_CAPACITY_MINUTES,
        );
    }
}
