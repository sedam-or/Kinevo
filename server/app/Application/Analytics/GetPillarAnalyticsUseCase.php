<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\PillarAnalytics;
use App\Domain\Analytics\Pillar;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use App\Domain\Tasks\Contracts\TaskRepository;
use Carbon\CarbonImmutable;

/**
 * Four-pillar realization read model (FR-12, TASK-133): aggregates completed
 * task minutes per pillar over the period and compares them against the mapped
 * program weekly targets. Pillars are determined through program category
 * mapping; Uncategorized is reserved for tasks without a mapping (FR-12
 * Business Rules). Division by zero target yields N/A (null), never NaN.
 */
final readonly class GetPillarAnalyticsUseCase
{
    public function __construct(
        private ProgressEventRepository $progressEvents,
        private TaskRepository $tasks,
        private ProgramRepository $programs,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): PillarAnalytics
    {
        $weeks = max(1, (int) ceil($from->startOfDay()->diffInDays($to->endOfDay()) / 7));

        $programPillars = [];
        $targetByPillar = [];
        foreach ($this->programs->listForUser($userId) as $program) {
            $pillar = Pillar::fromCategory($program->category);
            $programPillars[$program->id] = $pillar->value;

            if ($program->weeklyTargetMinutes !== null) {
                $targetByPillar[$pillar->value] = ($targetByPillar[$pillar->value] ?? 0)
                    + $program->weeklyTargetMinutes * $weeks;
            }
        }

        $completedIds = [];
        foreach ($this->progressEvents->listForUser($userId, $from, $to, ProgressEventType::TASK_COMPLETED) as $event) {
            if ($event->entityType === 'task') {
                $completedIds[$event->entityId] = true;
            }
        }

        $realizationByPillar = [];
        if ($completedIds !== []) {
            foreach ($this->tasks->listForUser($userId) as $task) {
                if (! isset($completedIds[$task->id])) {
                    continue;
                }
                $pillar = isset($programPillars[$task->programId])
                    ? $programPillars[$task->programId]
                    : Pillar::UNCATEGORIZED;
                $realizationByPillar[$pillar] = ($realizationByPillar[$pillar] ?? 0)
                    + ($task->estimatedMinutes ?? 0);
            }
        }

        $rows = [];
        $keys = array_merge(Pillar::canonical(), [Pillar::UNCATEGORIZED]);
        foreach ($keys as $key) {
            $realization = $realizationByPillar[$key] ?? 0;
            $target = $targetByPillar[$key] ?? 0;

            $rows[] = [
                'key' => $key,
                'label' => (new Pillar($key))->label(),
                'realization_minutes' => $realization,
                'target_minutes' => $target,
                'percent' => $target > 0 ? round($realization / $target, 4) : null,
            ];
        }

        return new PillarAnalytics($from->toDateString(), $to->toDateString(), $rows);
    }
}
