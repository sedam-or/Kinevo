<?php

namespace App\Application\Ai;

use App\Application\Milestones\CreateMilestoneUseCase;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Milestones\Milestone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Accept a pending proposal and apply it as a domain mutation (FR-62).
 *
 * For goal breakdown proposals this creates the proposed Milestones for the
 * referenced Goal within a transaction (SRS Transaction rule). Rejecting or
 * editing is the UI path; accept applies exactly the validated payload.
 *
 * Only proposals that passed structured validation (FR-61) exist in the table,
 * so accepting never persists malformed AI output.
 */
final readonly class AcceptAiProposalUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
        private CreateMilestoneUseCase $createMilestone,
    ) {}

    /**
     * @return array<int, Milestone> created milestones
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

        if (! $proposal->type->equals(new AiProposalType(AiProposalType::GOAL_BREAKDOWN))) {
            throw new InvalidArgumentException('This proposal type is not yet supported.');
        }

        $goalId = (int) $proposal->payload['goal_id'];

        // Transaction boundary (SRS Transaction rule): milestones + the
        // decision flip must succeed or fail together.
        return DB::transaction(fn () => $this->applyGoalBreakdown($userId, $proposal, $goalId));
    }

    /**
     * @return array<int, Milestone>
     */
    private function applyGoalBreakdown(int $userId, AiProposal $proposal, int $goalId): array
    {
        $created = [];

        foreach ($proposal->payload['milestones'] as $milestone) {
            $created[] = $this->createMilestone->__invoke(
                $userId,
                $goalId,
                $milestone['title'],
                null,
                null,
                isset($milestone['target_date']) ? CarbonImmutable::parse($milestone['target_date']) : null,
                $milestone['estimated_minutes'] ?? null,
            );
        }

        $this->proposals->updateDecision(
            $proposal->withDecision('accepted', "goal:breakdown:{$proposal->id}"),
        );

        return $created;
    }
}
