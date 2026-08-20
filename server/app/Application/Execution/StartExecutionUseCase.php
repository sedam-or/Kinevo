<?php

namespace App\Application\Execution;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Application\Tasks\GetTaskUseCase;
use App\Application\Tasks\SetTaskStatusUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Start an execution timer for a task (TASK-120). The timer is persisted, its
 * state derived from persisted timestamps (FR-05). Starting moves the task to
 * `in_progress` and appends a `task_started` activity event.
 */
final readonly class StartExecutionUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
        private GetTaskUseCase $getTask,
        private SetTaskStatusUseCase $setTaskStatus,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId, int $taskId, CarbonImmutable $now): ExecutionSession
    {
        $task = $this->getTask->__invoke($userId, $taskId);

        if ($this->sessions->findActiveForUser($userId) !== null) {
            throw new InvalidArgumentException('An execution timer is already running.');
        }

        if (! $task->status->equals(TaskStatus::inProgress())) {
            $this->setTaskStatus->__invoke($userId, $taskId, TaskStatus::inProgress());
        }

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::taskStarted(),
            'task',
            $taskId,
            $task->title,
            operationId: "execution:started:{$taskId}:{$now->getTimestamp()}",
            payload: ['status' => 'running'],
        ));

        return $this->sessions->create(ExecutionSession::start($userId, $taskId, $now));
    }
}
