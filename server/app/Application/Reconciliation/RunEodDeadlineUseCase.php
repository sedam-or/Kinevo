<?php

namespace App\Application\Reconciliation;

use App\Domain\Reconciliation\EndOfDayReconciliationService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;

/**
 * 23:59 end-of-day deadline (FR-47): unresponsive eligible tasks become
 * Terlewat (missed). Idempotent via the Task state machine — a retry finds
 * nothing eligible and changes nothing.
 */
final readonly class RunEodDeadlineUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private EndOfDayReconciliationService $service,
    ) {}

    /**
     * @return array<int, Task> tasks reconciled (now missed)
     */
    public function __invoke(int $userId): array
    {
        $reconciled = $this->service->reconcileAtDeadline($this->tasks->listForUser($userId));

        foreach ($reconciled as $task) {
            $this->tasks->update($task);
        }

        return $reconciled;
    }
}
