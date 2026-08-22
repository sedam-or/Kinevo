<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\Entities\AiProposal;

interface AiProposalRepository
{
    public function persist(AiProposal $proposal): AiProposal;

    public function findForUser(int $userId, int $proposalId): ?AiProposal;

    /**
     * @return array<int, AiProposal>
     */
    public function listForUser(int $userId, ?string $proposalType = null, ?string $decision = null, int $limit = 50): array;

    public function updateDecision(AiProposal $proposal): AiProposal;

    public function updatePayload(AiProposal $proposal): AiProposal;
}
