<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * Updates editable fields of an existing task (FR-09/FR-45).
 */
final readonly class UpdateTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        ?string $title = null,
        ?string $description = null,
        ?int $programId = null,
        ?int $goalId = null,
        ?int $milestoneId = null,
        ?int $priorityTier = null,
        ?int $estimatedMinutes = null,
        ?CarbonImmutable $dueAt = null,
    ): Task {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        if ($title !== null) {
            $task = $task->withTitle($title);
        }
        if ($description !== null) {
            $task = $task->withDescription($description);
        }
        if ($programId !== null || $goalId !== null || $milestoneId !== null) {
            $task = $task->withContext(
                $programId ?? $task->programId,
                $goalId ?? $task->goalId,
                $milestoneId ?? $task->milestoneId,
            );
        }
        if ($priorityTier !== null) {
            $task = $task->withPriorityTier($priorityTier);
        }
        if ($estimatedMinutes !== null) {
            $task = $task->withEstimatedMinutes($estimatedMinutes);
        }
        if ($dueAt !== null) {
            $task = $task->withDueAt($dueAt);
        }

        return $this->tasks->update($task);
    }
}
