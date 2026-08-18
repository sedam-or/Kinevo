<?php

namespace App\Application\Tasks;

use App\Domain\Tasks\Contracts\SubtaskRepository;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Subtask;

/**
 * Adds a checklist subtask to a task (FR-09). No deeper hierarchy (FR-45).
 */
final readonly class AddSubtaskUseCase
{
    public function __construct(
        private SubtaskRepository $subtasks,
        private TaskRepository $tasks,
    ) {}

    public function __invoke(
        int $userId,
        int $taskId,
        string $title,
        ?string $notes = null,
        ?int $sequence = null,
    ): Subtask {
        (new GetTaskUseCase($this->tasks))($userId, $taskId);

        $existing = $this->subtasks->listForTask($userId, $taskId);
        $nextSequence = $sequence ?? $this->nextSequence($existing);

        $subtask = Subtask::create($userId, $taskId, $title, $notes, $nextSequence);

        return $this->subtasks->create($userId, $subtask);
    }

    /**
     * @param  array<int, Subtask>  $existing
     */
    private function nextSequence(array $existing): int
    {
        $max = 0;
        foreach ($existing as $subtask) {
            $max = max($max, $subtask->sequence);
        }

        return $max + 1;
    }
}
