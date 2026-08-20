<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\HeatmapAnalytics;
use App\Domain\Analytics\Pillar;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use Carbon\CarbonImmutable;

/**
 * Annual activity heatmap (FR-31, TASK-134): daily intensity from completion
 * and recharge (plus productive time and progress events), optionally filtered
 * to a single pillar. The metric definition is fixed and documented so it stays
 * stable within a report version; every day carries exact values for
 * accessible alternatives, and missing dates report zero with a readable label.
 */
final readonly class GetHeatmapAnalyticsUseCase
{
    private const COMPLETION_WEIGHT = 30;

    private const PROGRESS_WEIGHT = 10;

    public function __construct(
        private ProgressEventRepository $progressEvents,
        private FocusSessionRepository $focusSessions,
        private RechargeSessionRepository $recharges,
        private TaskRepository $tasks,
        private ProgramRepository $programs,
    ) {}

    public function __invoke(
        int $userId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?Pillar $pillar = null,
    ): HeatmapAnalytics {
        $taskPillars = $this->taskPillars($userId);
        $allowed = $this->allowedTaskIds($taskPillars, $pillar);

        // Bulk progress events grouped by day (one query, then grouped client-side).
        $eventsByDay = [];
        foreach ($this->progressEvents->listForUser($userId, $from->startOfDay(), $to->endOfDay(), null, 10000) as $event) {
            if ($allowed !== null && ! isset($allowed[$event->entityId])) {
                continue;
            }
            $day = $event->occurredAt->toDateString();
            $eventsByDay[$day] ??= ['completion' => 0, 'progress' => 0];
            $eventsByDay[$day]['progress']++;
            if ($event->eventType->value === ProgressEventType::TASK_COMPLETED && $event->entityType === 'task') {
                $eventsByDay[$day]['completion']++;
            }
        }

        $days = [];
        $cursor = $from->startOfDay();
        while ($cursor->lte($to->endOfDay())) {
            $date = $cursor->toDateString();
            $dayStart = $cursor;
            $dayEnd = $cursor->endOfDay();

            $productive = $this->focusSessions->sumDurationMinutesBetween($userId, $dayStart, $dayEnd);
            $recharge = $this->recharges->sumCompletedMinutesBetween($userId, $dayStart, $dayEnd);
            $completion = $eventsByDay[$date]['completion'] ?? 0;
            $progress = $eventsByDay[$date]['progress'] ?? 0;

            $days[] = [
                'date' => $date,
                'productive_minutes' => $productive,
                'recharge_minutes' => $recharge,
                'completion_count' => $completion,
                'progress_events' => $progress,
                'intensity' => $this->intensity($productive, $recharge, $completion, $progress),
            ];

            $cursor = $cursor->addDay();
        }

        return new HeatmapAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            $pillar?->value,
            $days,
            $this->legend(),
        );
    }

    /**
     * Fixed, documented metric (stable within a report version, FR-31 Business
     * Rules): score = productive + recharge + completion*30 + progress*10.
     * Levels: 0 none, 1 low, 2 medium, 3 high, 4 very high.
     */
    private function intensity(int $productive, int $recharge, int $completion, int $progress): int
    {
        $score = $productive + $recharge + $completion * self::COMPLETION_WEIGHT + $progress * self::PROGRESS_WEIGHT;

        if ($score <= 0) {
            return 0;
        }
        if ($score < 60) {
            return 1;
        }
        if ($score < 120) {
            return 2;
        }
        if ($score < 240) {
            return 3;
        }

        return 4;
    }

    /**
     * @return array<int, array{level: int, label: string, description: string}>
     */
    private function legend(): array
    {
        return [
            ['level' => 0, 'label' => 'None', 'description' => 'No tracked activity'],
            ['level' => 1, 'label' => 'Low', 'description' => 'A little activity'],
            ['level' => 2, 'label' => 'Medium', 'description' => 'Moderate activity'],
            ['level' => 3, 'label' => 'High', 'description' => 'High activity'],
            ['level' => 4, 'label' => 'Very high', 'description' => 'Very high activity'],
        ];
    }

    /**
     * @return array<int, string> task id → pillar value
     */
    private function taskPillars(int $userId): array
    {
        $programPillars = [];
        foreach ($this->programs->listForUser($userId) as $program) {
            $programPillars[$program->id] = Pillar::fromCategory($program->category)->value;
        }

        $taskPillars = [];
        foreach ($this->tasks->listForUser($userId) as $task) {
            $taskPillars[$task->id] = $task->programId !== null && isset($programPillars[$task->programId])
                ? $programPillars[$task->programId]
                : Pillar::UNCATEGORIZED;
        }

        return $taskPillars;
    }

    /**
     * Task ids allowed by the pillar filter. null means all pillars.
     *
     * @param  array<int, string>  $taskPillars
     * @return array<int, true>|null
     */
    private function allowedTaskIds(array $taskPillars, ?Pillar $pillar): ?array
    {
        if ($pillar === null) {
            return null;
        }

        $allowed = [];
        foreach ($taskPillars as $taskId => $value) {
            if ($value === $pillar->value) {
                $allowed[$taskId] = true;
            }
        }

        return $allowed;
    }
}
