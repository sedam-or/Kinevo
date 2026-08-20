<?php

namespace App\Application\Execution;

use App\Application\Tasks\PartialCompleteTaskUseCase;
use App\Application\Tasks\SetTaskStatusUseCase;
use App\Domain\Execution\Contracts\ExecutionSessionRepository;
use App\Domain\Execution\ExecutionSession;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Focus\FocusSession;
use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\ValueObjects\TaskStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Complete an execution timer (TASK-120). Records the FocusSession from the
 * tracked duration (FR-05: the recorded duration is the tracked duration, not
 * the wall-clock interval — pauses are excluded), then transitions the task:
 * completed when no subtasks remain (SetTaskStatusUseCase → activity + progress
 * event), otherwise partial-completed (PartialCompleteTaskUseCase → continued
 * with a scheduled continuation).
 *
 * @return array{execution: ExecutionSession, focus_session: FocusSession, task: Task, continuation: Task|null}
 */
final readonly class CompleteExecutionUseCase
{
    public function __construct(
        private ExecutionSessionRepository $sessions,
        private FocusSessionRepository $focusSessions,
        private SetTaskStatusUseCase $setTaskStatus,
        private PartialCompleteTaskUseCase $partialComplete,
        private SubtaskRepository $subtasks,
    ) {}

    public function __invoke(int $userId, int $sessionId, CarbonImmutable $now): array
    {
        $session = $this->sessions->findForUser($userId, $sessionId);

        if ($session === null) {
            throw new InvalidArgumentException('Execution session not found.');
        }

        $completed = $session->complete($now);
        $saved = $this->sessions->update($completed);

        $trackedSeconds = $saved->elapsedSeconds($now);
        $focus = $this->focusSessions->create(FocusSession::fromTracked(
            $userId,
            $saved->startedAt,
            $now,
            $trackedSeconds,
            $saved->taskId,
        ));

        $remaining = $this->subtasks->listForTask($userId, $saved->taskId);

        if ($remaining === []) {
            $task = $this->setTaskStatus->__invoke($userId, $saved->taskId, TaskStatus::completed());

            return ['execution' => $saved, 'focus_session' => $focus, 'task' => $task, 'continuation' => null];
        }

        $result = $this->partialComplete->__invoke($userId, $saved->taskId);

        return [
            'execution' => $saved,
            'focus_session' => $focus,
            'task' => $result['task'],
            'continuation' => $result['continuation'],
        ];
    }
}
