<?php

namespace App\Domain\Ai\ValueObjects;

/**
 * A validated structured AI proposal (SRS FR-61). Only instances that passed
 * the versioned schema may be considered for any domain mutation flow.
 */
final readonly class ValidatedAiProposal
{
    public function __construct(
        public AiProposalType $type,
        public int $schemaVersion,
        public array $payload,
    ) {}
}
