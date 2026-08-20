<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\ActivityAnalytics;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use Carbon\CarbonImmutable;

/**
 * Activity read model (TASK-130): the append-only activity log grouped by event
 * type over the period plus a small recency sample.
 */
final readonly class GetActivityAnalyticsUseCase
{
    public const RECENT_SAMPLE_LIMIT = 10;

    public function __construct(
        private ActivityLogRepository $activityLogs,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): ActivityAnalytics
    {
        $all = $this->activityLogs->exportForUser($userId, $from, $to);

        $byType = [];
        $recent = [];
        foreach ($all as $log) {
            $byType[$log->eventType->value] = ($byType[$log->eventType->value] ?? 0) + 1;
            if (count($recent) < self::RECENT_SAMPLE_LIMIT) {
                $recent[] = [
                    'id' => $log->id,
                    'event_type' => $log->eventType->value,
                    'entity_type' => $log->entityType,
                    'entity_id' => $log->entityId,
                    'title' => $log->title,
                    'event_at' => $log->eventAt->toIso8601String(),
                ];
            }
        }

        return new ActivityAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            count($all),
            $byType,
            $recent,
        );
    }
}
