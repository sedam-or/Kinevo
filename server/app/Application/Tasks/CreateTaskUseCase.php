<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * Creates a single executable task (FR-09/FR-45). Optional Program/Goal/Milestone context.
 */
final readonly class CreateTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        ?string $description = null,
        ?int $programId = null,
        ?int $goalId = null,
        ?int $milestoneId = null,
        int $priorityTier = 3,
        ?int $estimatedMinutes = null,
        ?CarbonImmutable $dueAt = null,
    ): Task {
        $task = Task::create(
            $userId,
            $title,
            $description,
            $programId,
            $goalId,
            $milestoneId,
            $priorityTier,
            $estimatedMinutes,
            $dueAt,
        );

        return $this->tasks->create($userId, $task);
    }
}
