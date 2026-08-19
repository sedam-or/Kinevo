<?php

namespace App\Application\Ai;

use App\Application\Tasks\CreateTaskUseCase;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Accept a pending note task-extraction proposal and create the Tasks (FR-62,
 * §17.4 golden flow 5: note → extract task proposal → review → create Task).
 * Tasks are created within a transaction with the decision flip.
 */
final readonly class AcceptNoteTaskExtractionUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
        private CreateTaskUseCase $createTask,
    ) {}

    /**
     * @return array<int, Task>
     */
    public function __invoke(int $userId, int $proposalId): array
    {
        $proposal = $this->proposals->findForUser($userId, $proposalId);

        if ($proposal === null) {
            throw new InvalidArgumentException('AI proposal not found.');
        }

        if (! $proposal->isPending()) {
            throw new InvalidArgumentException('AI proposal is not pending.');
        }

        if (! $proposal->type->equals(new AiProposalType(AiProposalType::TASK_EXTRACTION))) {
            throw new InvalidArgumentException('Proposal is not a task extraction proposal.');
        }

        return DB::transaction(fn () => $this->apply($userId, $proposal));
    }

    /**
     * @return array<int, Task>
     */
    private function apply(int $userId, AiProposal $proposal): array
    {
        $created = [];

        foreach ($proposal->payload['tasks'] as $task) {
            $created[] = $this->createTask->__invoke(
                $userId,
                $task['title'],
                null,
                null,
                null,
                null,
                3,
                $task['estimated_minutes'] ?? null,
                isset($task['due_at']) ? CarbonImmutable::parse($task['due_at']) : null,
            );
        }

        $this->proposals->updateDecision(
            $proposal->withDecision('accepted', "note:task-extraction:{$proposal->id}"),
        );

        return $created;
    }
}
