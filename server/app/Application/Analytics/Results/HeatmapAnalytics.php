<?php

namespace App\Application\Analytics\Results;

/**
 * Heatmap read model (FR-31, TASK-134): per-day activity intensity derived from
 * productive time, recharge, task completion, and progress events, with an
 * optional pillar filter. The metric definition is fixed (stable within a
 * report version); each day carries exact values for accessible alternatives.
 *
 * @phpstan-type HeatmapDay array{date: string, productive_minutes: int, recharge_minutes: int, completion_count: int, progress_events: int, intensity: int}
 * @phpstan-type HeatmapLegend array<int, array{level: int, label: string, description: string}>
 */
final readonly class HeatmapAnalytics
{
    /**
     * @param  array<int, HeatmapDay>  $days
     * @param  HeatmapLegend  $legend
     */
    public function __construct(
        public string $from,
        public string $to,
        public ?string $pillar,
        public array $days,
        public array $legend,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'pillar' => $this->pillar,
            'days' => $this->days,
            'legend' => $this->legend,
        ];
    }
}
