<?php

namespace App\Application\Tasks;

use App\Application\Workspaces\ResolveWorkspaceContext;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Creates a single executable task (FR-09/FR-45). Optional Program/Goal/Milestone context.
 */
final readonly class CreateTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private GoalRepository $goals,
        private ResolveWorkspaceContext $workspaceContext,
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
        mixed $workspaceId = null,
        bool $isSacredAnchor = false,
    ): Task {
        // TASK-P19-013 — precedence: explicit parent context > explicit
        // workspace > owner default; conflicts are rejected server-side.
        [$programId, $goalId, $milestoneId, $workspaceId] = $this->resolveContext($userId, $programId, $goalId, $milestoneId, $workspaceId);

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

        if ($workspaceId !== null) {
            $task = $task->withWorkspace($workspaceId);
        }

        if ($isSacredAnchor) {
            $task = $task->withSacredAnchor(true);
            $this->assertSingleSacredAnchor($userId, $task);
        }

        return $this->tasks->create($userId, $task);
    }

    /**
     * ADR-016 §2.10 — at most one Sacred Anchor task per user.
     */
    private function assertSingleSacredAnchor(int $userId, Task $task): void
    {
        foreach ($this->tasks->listForUser($userId) as $other) {
            if ($other->id !== $task->id && $other->isSacredAnchor) {
                throw new InvalidArgumentException('A user can have only one Sacred Anchor task.');
            }
        }
    }

    /**
     * TASK-P19-013 — a task linked to a Goal inherits the goal's workspace;
     * an explicit workspace that CONFLICTS with the linked goal is rejected.
     * Without parent context the shared resolver applies (validated explicit
     * or owner default).
     *
     * @return array{0: ?int, 1: ?int, 2: ?int, 3: int|null}
     */
    private function resolveContext(int $userId, ?int $programId, ?int $goalId, ?int $milestoneId, mixed $requestedWorkspaceId): array
    {
        if ($goalId !== null) {
            $goal = $this->goals->findForUser($userId, $goalId);
            if ($goal?->workspaceId !== null) {
                $explicit = is_numeric($requestedWorkspaceId) ? (int) $requestedWorkspaceId : null;
                if ($explicit !== null && $explicit !== $goal->workspaceId) {
                    throw new InvalidArgumentException('Task workspace conflicts with the linked goal workspace.');
                }

                return [$programId, $goalId, $milestoneId, $goal->workspaceId];
            }
        }

        return [$programId, $goalId, $milestoneId, $this->workspaceContext->forWrite($userId, $requestedWorkspaceId)];
    }
}
