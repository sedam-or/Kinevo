<?php

namespace App\Application\Analytics\Results;

/**
 * Activity read model (TASK-130): the append-only activity log grouped by event
 * type over the period plus a small recency sample.
 *
 * @phpstan-type ActivityEventCounts array<string, int>
 * @phpstan-type ActivitySample array{id: int|null, event_type: string, entity_type: string, entity_id: int, title: string|null, event_at: string}
 */
final readonly class ActivityAnalytics
{
    /**
     * @param  ActivityEventCounts  $byType
     * @param  array<int, ActivitySample>  $recent
     */
    public function __construct(
        public string $from,
        public string $to,
        public int $totalEvents,
        public array $byType,
        public array $recent,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'total_events' => $this->totalEvents,
            'by_type' => $this->byType,
            'recent' => $this->recent,
        ];
    }
}
