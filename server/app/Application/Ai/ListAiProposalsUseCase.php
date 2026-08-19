<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;

/**
 * List the owner's proposals, optionally filtered by type/decision (FR-62
 * proposal inbox).
 */
final readonly class ListAiProposalsUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
    ) {}

    /**
     * @return array<int, AiProposal>
     */
    public function __invoke(
        int $userId,
        ?string $proposalType = null,
        ?string $decision = null,
        int $limit = 50,
    ): array {
        return $this->proposals->listForUser($userId, $proposalType, $decision, $limit);
    }
}
