<?php

namespace App\Application\Reconciliation;

use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Reconciliation\EndOfDayReconciliationService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * 21:00 end-of-day scan (FR-47/FR-35): create ONE reconciliation notification
 * per user/local-day listing untouched tasks. Idempotent: a retry returns the
 * existing notification and never creates a duplicate. No untouched tasks →
 * no notification (FR-35 Alternative Flow).
 *
 * During an Emergency Pause week or an active Break Mode period the
 * notification is suppressed while the pause event / break period preserves
 * audit data (FR-47 Business Rules; FR-36/FR-49).
 */
final readonly class RunEodPromptUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private NotificationRepository $notifications,
        private PauseEventRepository $pauseEvents,
        private BreakPeriodRepository $breaks,
        private EndOfDayReconciliationService $service,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $localDate): ?Notification
    {
        if ($this->pauseEvents->isWeekExceptional($userId, $localDate)) {
            return null;
        }

        if ($this->breaks->coversWeek($userId, $localDate)) {
            return null;
        }

        $existing = $this->notifications->findReconciliationForDay($userId, $localDate);
        if ($existing !== null) {
            return $existing;
        }

        $eligible = $this->service->promptTasks($this->tasks->listForUser($userId));

        if ($eligible === []) {
            return null;
        }

        $payload = array_map(static fn (Task $task) => [
            'task_id' => $task->id,
            'title' => $task->title,
            'status' => $task->status->value,
        ], $eligible);

        return $this->notifications->create(Notification::create(
            $userId,
            NotificationType::reconciliation(),
            $localDate,
            'End-of-day reconciliation',
            $payload,
        ));
    }
}
