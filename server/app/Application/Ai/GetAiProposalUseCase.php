<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use InvalidArgumentException;

/**
 * Return one proposal scoped to the owner (SRS §15.1, FR-62 preview).
 */
final readonly class GetAiProposalUseCase
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

        return $proposal;
    }
}
