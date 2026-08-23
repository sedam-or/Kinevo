<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiOutputException;
use App\Domain\Ai\AiSchemaRegistry;
use App\Domain\Ai\AiSchemaRules;
use App\Domain\Ai\Contracts\AiProposalRepository;
use App\Domain\Ai\Entities\AiProposal;
use App\Domain\Ai\ValueObjects\AiProposalType;
use InvalidArgumentException;

/**
 * Apply user edits to a PENDING (or already-edited) proposal payload (FR-52,
 * FR-62). The edited payload MUST pass the same schema validation as AI
 * output (FR-61): editing never bypasses the validated proposal contract, it
 * only changes WHAT will be applied when the user accepts. The proposal stays
 * out of the domain until acceptance; decision becomes `edited` so the audit
 * trail shows the user changed the AI output before applying it.
 */
final readonly class UpdateAiProposalUseCase
{
    public function __construct(
        private AiProposalRepository $proposals,
        private AiSchemaRegistry $registry,
        private AiSchemaRules $rules,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __invoke(int $userId, int $proposalId, array $payload): AiProposal
    {
        $proposal = $this->proposals->findForUser($userId, $proposalId);
        if ($proposal === null) {
            throw new InvalidArgumentException('AI proposal not found.');
        }
        if (! $proposal->type->equals(new AiProposalType(AiProposalType::GOAL_BREAKDOWN))) {
            throw new InvalidArgumentException('Only goal breakdown proposals can be edited.');
        }
        if (! $proposal->isApplicable()) {
            throw new InvalidArgumentException('This proposal was already decided and can no longer be edited.');
        }

        $schema = $this->registry->schemaFor($proposal->type);
        try {
            $this->rules->validate($payload, $schema['fields']);
        } catch (AiOutputException $e) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }

        if ((int) ($payload['goal_id'] ?? 0) !== (int) ($proposal->payload['goal_id'] ?? 0)) {
            throw new InvalidArgumentException('The proposed goal cannot be changed.');
        }

        return $this->proposals->updatePayload($proposal->withPayload($payload));
    }
}
