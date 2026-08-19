<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use InvalidArgumentException;

/**
 * Reject a pending proposal (FR-62). Rejection creates no domain mutation
 * (FR-62 acceptance criterion).
 */
final readonly class RejectAiProposalUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
    ) {}

    public function __invoke(int $userId, int $proposalId): AiProposal
    {
        $proposal = $this->proposals->findForUser($userId, $proposalId);

        if ($proposal === null) {
            throw new InvalidArgumentException('AI proposal not found.');
        }

        if (! $proposal->isPending()) {
            throw new InvalidArgumentException('AI proposal is not pending.');
        }

        return $this->proposals->updateDecision($proposal->withDecision('rejected'));
    }
}
