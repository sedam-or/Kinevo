<?php

namespace App\Application\Reconciliation;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Reconciliation\EndOfDayReconciliationService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * 21:00 end-of-day scan (FR-47/FR-35): create ONE reconciliation notification
 * per user/local-day listing untouched tasks. Idempotent: a retry returns the
 * existing notification and never creates a duplicate. No untouched tasks →
 * no notification (FR-35 Alternative Flow).
 */
final readonly class RunEodPromptUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private NotificationRepository $notifications,
        private EndOfDayReconciliationService $service,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $localDate): ?Notification
    {
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
