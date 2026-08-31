<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskVersionConflict;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

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
        ?bool $isSacredAnchor = null,
        ?int $baseVersion = null,
    ): Task {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        // ADR-017 §2.11 — optimistic concurrency guard: a provided base_version
        // that no longer matches the current task version is a deterministic
        // conflict (409), never a silent overwrite of newer state.
        if ($baseVersion !== null && $task->version !== $baseVersion) {
            throw new TaskVersionConflict($baseVersion, $task->version);
        }

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
        if ($isSacredAnchor !== null) {
            $task = $task->withSacredAnchor($isSacredAnchor);
            $this->assertSingleSacredAnchor($userId, $task);
        }

        // ADR-017 §2.11 — a content update is a new version (conflict guard).
        $task = $task->withBumpedVersion();

        return $this->tasks->update($task);
    }

    /**
     * ADR-016 §2.10 — at most one Sacred Anchor task per user.
     */
    private function assertSingleSacredAnchor(int $userId, Task $task): void
    {
        if (! $task->isSacredAnchor) {
            return;
        }

        foreach ($this->tasks->listForUser($userId) as $other) {
            if ($other->id !== $task->id && $other->isSacredAnchor) {
                throw new InvalidArgumentException('A user can have only one Sacred Anchor task.');
            }
        }
    }
}
