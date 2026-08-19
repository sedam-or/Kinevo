<?php

namespace App\Application\Recovery;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Application\Tasks\GetTaskUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Reconciliation\MorningRecoveryService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Resolve a recovered task (FR-48): reschedule to today, mark complete, or
 * keep in backlog. Only `missed` tasks are recoverable; transitions are
 * validated by the Task state machine. Reschedule is rejected for tasks whose
 * program is Dropped/Completed (FR-48 Exception Flow — manual disposition only).
 */
final readonly class ResolveRecoveredTaskUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ProgramRepository $programs,
        private MorningRecoveryService $service,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        string $action,
        ?CarbonImmutable $dueAt = null,
    ): Task {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        if (! $task->status->equals(TaskStatus::missed())) {
            throw new InvalidArgumentException('Task is not in recovery (missed).');
        }

        $program = $task->programId !== null
            ? $this->programs->findForUser($userId, $task->programId)
            : null;

        if (! in_array($action, $this->service->allowedActions($task, $program), true)) {
            throw new InvalidArgumentException("Recovery action '{$action}' is not available for this task.");
        }

        $status = match ($action) {
            MorningRecoveryService::ACTION_RESCHEDULE => TaskStatus::scheduled(),
            MorningRecoveryService::ACTION_COMPLETE => TaskStatus::completed(),
            MorningRecoveryService::ACTION_BACKLOG => TaskStatus::backlog(),
            default => throw new InvalidArgumentException("Unknown recovery action: {$action}"),
        };

        $updated = $task->withStatus($status);

        if ($action === MorningRecoveryService::ACTION_RESCHEDULE && $dueAt !== null) {
            $updated = $updated->withDueAt($dueAt);
        }

        $saved = $this->tasks->update($updated);

        if ($saved->isCompleted()) {
            $this->recordActivity->__invoke(ActivityLog::create(
                $userId,
                ActivityEventType::taskCompleted(),
                'task',
                $saved->id,
                $saved->title,
                operationId: "task:completed:{$saved->id}",
                payload: ['status' => $saved->status->value, 'progress' => $saved->progress],
            ));
        }

        return $saved;
    }
}
