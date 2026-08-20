<?php

namespace App\Application\Analytics\Results;

/**
 * Capacity read model (TASK-130/TASK-132): the Capacity feedback-loop estimate
 * (FR-49), per-day capacity indicators, realization ratio, and the weekly trend.
 * Reuses the existing scheduling primitives — the algorithm is never recreated.
 *
 * @phpstan-type CapacityWeek array{week_start: string, planned_minutes: int, completed_minutes: int, realization: float, tag: string}
 * @phpstan-type CapacityDay array{date: string, scheduled_minutes: int, available_minutes: int, overload_minutes: int, status: string}
 */
final readonly class CapacityAnalytics
{
    /**
     * @param  array<int, CapacityWeek>  $weeks
     * @param  array<int, CapacityDay>  $days
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $weeks,
        public array $days,
        public float $realizationRatio,
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
            'from' => $this->from,
            'to' => $this->to,
            'weeks' => $this->weeks,
            'days' => $this->days,
            'realization_ratio' => $this->realizationRatio,
            'average_realization' => $this->averageRealization,
            'confidence' => $this->confidence,
            'recommendation' => $this->recommendation,
            'reason' => $this->reason,
            'target_capacity_minutes' => $this->targetCapacityMinutes,
        ];
    }
}
