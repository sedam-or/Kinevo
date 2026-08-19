<?php

namespace App\Application\Tasks;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Application\Progress\RecordProgressEventUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Progress\ProgressEventService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;

/**
 * Applies an explicit state transition to a task (FR-09 lifecycle).
 * Completing a task appends exactly one activity event (FR-34) and one
 * meaningful progress event (SRS §6.8/§12.5).
 */
final readonly class SetTaskStatusUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private RecordActivityUseCase $recordActivity,
        private RecordProgressEventUseCase $recordProgressEvent,
        private ProgressEventService $progressEvents,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        TaskStatus $status,
    ): Task {
        $task = (new GetTaskUseCase($this->tasks))($userId, $taskId);

        $updated = $task->withStatus($status);

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

            $this->recordProgressEvent->__invoke($this->progressEvents->taskCompleted(
                $userId,
                $saved->id,
                $saved->title,
            ));
        }

        return $saved;
    }
}
