<?php

namespace App\Domain\Tasks;

use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Task aggregate — single executable work item (FR-09, FR-45, §6.5 Task semantics).
 * MAY reference Program/Goal/Milestone context, due date, duration.
 * Immutable value semantics: state changes return a new instance.
 */
final class Task
{
    private const PROGRESS_MODES = ['derived', 'manual'];

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly ?int $programId,
        public readonly ?int $goalId,
        public readonly ?int $milestoneId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly TaskStatus $status,
        public readonly int $priorityTier,
        public readonly ?int $estimatedMinutes,
        public readonly ?CarbonImmutable $dueAt,
        public readonly string $progressMode,
        public readonly int $progress,
        public readonly int $version,
        public readonly ?int $workspaceId = null,
    ) {}

    public static function create(
        int $userId,
        string $title,
        ?string $description = null,
        ?int $programId = null,
        ?int $goalId = null,
        ?int $milestoneId = null,
        int $priorityTier = 3,
        ?int $estimatedMinutes = null,
        ?CarbonImmutable $dueAt = null,
        string $progressMode = 'derived',
    ): self {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title is required.');
        }

        if (! in_array($progressMode, self::PROGRESS_MODES, true)) {
            throw new InvalidArgumentException("Unsupported progress mode: {$progressMode}");
        }

        return new self(
            0,
            $userId,
            $programId,
            $goalId,
            $milestoneId,
            trim($title),
            $description,
            TaskStatus::backlog(),
            $priorityTier,
            $estimatedMinutes,
            $dueAt,
            $progressMode,
            0,
            1,
        );
    }

    public function withId(int $id): self
    {
        return $this->reborn(['id' => $id]);
    }

    public function withTitle(string $title): self
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Task title is required.');
        }

        return $this->reborn(['title' => trim($title)]);
    }

    public function withDescription(?string $description): self
    {
        return $this->reborn(['description' => $description]);
    }

    public function withContext(
        ?int $programId = null,
        ?int $goalId = null,
        ?int $milestoneId = null,
    ): self {
        return $this->reborn([
            'programId' => $programId,
            'goalId' => $goalId,
            'milestoneId' => $milestoneId,
        ]);
    }

    public function withPriorityTier(int $priorityTier): self
    {
        if ($priorityTier < 1 || $priorityTier > 3) {
            throw new InvalidArgumentException('Priority tier must be between 1 and 3.');
        }

        return $this->reborn(['priorityTier' => $priorityTier]);
    }

    public function withEstimatedMinutes(?int $estimatedMinutes): self
    {
        if ($estimatedMinutes !== null && $estimatedMinutes <= 0) {
            throw new InvalidArgumentException('Estimated minutes must be positive.');
        }

        return $this->reborn(['estimatedMinutes' => $estimatedMinutes]);
    }

    public function withDueAt(?CarbonImmutable $dueAt): self
    {
        return $this->reborn(['dueAt' => $dueAt]);
    }

    /**
     * Transition to a new status, validating the Task state machine (domain-model).
     */
    public function withStatus(TaskStatus $status): self
    {
        if (! $this->status->canTransitionTo($status)) {
            throw new InvalidArgumentException(
                "Invalid task status transition: {$this->status->value} → {$status->value}"
            );
        }

        return $this->reborn(['status' => $status, 'version' => $this->version + 1]);
    }

    /**
     * Update derived/audited progress snapshot (0-100). FR-09: derived from subtasks.
     */
    public function withProgress(int $progress): self
    {
        if ($progress < 0 || $progress > 100) {
            throw new InvalidArgumentException('Progress must be between 0 and 100.');
        }

        return $this->reborn(['progress' => $progress]);
    }

    public function isCompleted(): bool
    {
        return $this->status->equals(TaskStatus::completed());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'program_id' => $this->programId,
            'goal_id' => $this->goalId,
            'milestone_id' => $this->milestoneId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority_tier' => $this->priorityTier,
            'estimated_minutes' => $this->estimatedMinutes,
            'due_at' => $this->dueAt?->toDateTimeString(),
            'progress_mode' => $this->progressMode,
            'progress' => $this->progress,
            'version' => $this->version,
            'workspace_id' => $this->workspaceId,
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function reborn(array $props): self
    {
        $merged = array_merge([
            'id' => $this->id,
            'userId' => $this->userId,
            'programId' => $this->programId,
            'goalId' => $this->goalId,
            'milestoneId' => $this->milestoneId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priorityTier' => $this->priorityTier,
            'estimatedMinutes' => $this->estimatedMinutes,
            'dueAt' => $this->dueAt,
            'progressMode' => $this->progressMode,
            'progress' => $this->progress,
            'version' => $this->version,
            'workspaceId' => $this->workspaceId,
        ], $props);

        return new self(
            $merged['id'],
            $merged['userId'],
            $merged['programId'],
            $merged['goalId'],
            $merged['milestoneId'],
            $merged['title'],
            $merged['description'],
            $merged['status'],
            $merged['priorityTier'],
            $merged['estimatedMinutes'],
            $merged['dueAt'],
            $merged['progressMode'],
            $merged['progress'],
            $merged['version'],
            $merged['workspaceId'],
        );
    }

    /** TASK-P19-013 — workspace context reference. */
    public function withWorkspace(int $workspaceId): self
    {
        return $this->reborn(['workspaceId' => $workspaceId]);
    }
}
