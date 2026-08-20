<?php

namespace App\Application\Analytics\Results;

/**
 * Capacity read model (TASK-130): the recent-week capacity samples and the
 * Capacity feedback-loop estimate (FR-49). Reuses WeekCapacitySampleProvider and
 * CapacityCalculator — the algorithm is never recreated here.
 *
 * @phpstan-type CapacityWeek array{week_start: string, planned_minutes: int, completed_minutes: int, realization: float, tag: string}
 */
final readonly class CapacityAnalytics
{
    /**
     * @param  array<int, CapacityWeek>  $weeks
     */
    public function __construct(
        public array $weeks,
        public float $averageRealization,
        public string $confidence,
        public string $recommendation,
        public string $reason,
        public int $targetCapacityMinutes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'weeks' => $this->weeks,
            'average_realization' => $this->averageRealization,
            'confidence' => $this->confidence,
            'recommendation' => $this->recommendation,
            'reason' => $this->reason,
            'target_capacity_minutes' => $this->targetCapacityMinutes,
        ];
    }
}
