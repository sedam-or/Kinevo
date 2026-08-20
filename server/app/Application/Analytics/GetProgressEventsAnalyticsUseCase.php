<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\ProgressEventsAnalytics;
use App\Domain\Progress\Contracts\ProgressEventRepository;
use Carbon\CarbonImmutable;

/**
 * Progress-events read model (TASK-130): meaningful progress events grouped by
 * event type over the period plus a small recency sample.
 */
final readonly class GetProgressEventsAnalyticsUseCase
{
    public const RECENT_SAMPLE_LIMIT = 10;

    public function __construct(
        private ProgressEventRepository $progressEvents,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): ProgressEventsAnalytics
    {
        $all = $this->progressEvents->listForUser($userId, $from, $to);

        $byType = [];
        $recent = [];
        foreach ($all as $event) {
            $byType[$event->eventType->value] = ($byType[$event->eventType->value] ?? 0) + 1;
            if (count($recent) < self::RECENT_SAMPLE_LIMIT) {
                $recent[] = [
                    'id' => $event->id,
                    'event_type' => $event->eventType->value,
                    'entity_type' => $event->entityType,
                    'entity_id' => $event->entityId,
                    'title' => $event->title,
                    'occurred_at' => $event->occurredAt->toIso8601String(),
                ];
            }
        }

        return new ProgressEventsAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            count($all),
            $byType,
            $recent,
        );
    }
}
