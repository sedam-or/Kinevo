<?php

namespace App\Application\Recovery;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use App\Domain\Reconciliation\MorningRecoveryService;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;

/**
 * Morning Recovery list (FR-48): previous-day Terlewat tasks ordered by nearest
 * deadline first, with the actions allowed per task. Tasks whose program is
 * Dropped/Completed are flagged with a reason and reschedule is withheld
 * (FR-48 Exception Flow).
 */
final readonly class GetRecoveryListUseCase
{
    public function __construct(
        private TaskRepository $tasks,
        private ProgramRepository $programs,
        private MorningRecoveryService $service,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(int $userId): array
    {
        $missed = $this->service->sortByDeadline($this->tasks->listMissedForUser($userId));

        /** @var array<int, Program> $programsById */
        $programsById = [];
        foreach ($this->programs->listForUser($userId) as $program) {
            $programsById[$program->id] = $program;
        }

        return array_map(function (Task $task) use ($programsById): array {
            $program = $task->programId !== null ? ($programsById[$task->programId] ?? null) : null;

            $data = $task->toArray();
            $data['allowed_actions'] = $this->service->allowedActions($task, $program);
            $data['invalid_reason'] = $this->service->programInvalidReason($task, $program);

            return $data;
        }, $missed);
    }
}
