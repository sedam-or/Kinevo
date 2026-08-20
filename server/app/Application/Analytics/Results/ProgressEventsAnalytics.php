<?php

namespace App\Application\Analytics\Results;

/**
 * Progress-events read model (TASK-130): meaningful progress events grouped by
 * event type over the period plus a small recency sample.
 *
 * @phpstan-type ProgressEventCounts array<string, int>
 * @phpstan-type ProgressEventSample array{id: int|null, event_type: string, entity_type: string, entity_id: int, title: string|null, occurred_at: string}
 */
final readonly class ProgressEventsAnalytics
{
    /**
     * @param  ProgressEventCounts  $byType
     * @param  array<int, ProgressEventSample>  $recent
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
