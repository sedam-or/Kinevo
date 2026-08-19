<?php

namespace App\Domain\Ai;

use App\Domain\Ai\ValueObjects\AiProposalType;
use App\Domain\Ai\ValueObjects\ValidatedAiProposal;
use JsonException;

/**
 * Parses and validates AI output against a versioned schema (SRS FR-61).
 * Malformed JSON or schema violations throw AiOutputException — the caller
 * must never persist anything that did not pass here.
 */
final readonly class StructuredAiOutputParser
{
    public function __construct(
        private AiSchemaRegistry $registry,
        private AiSchemaRules $rules,
    ) {}

    public function parse(AiProposalType $type, string $jsonText): ValidatedAiProposal
    {
        $decoded = $this->decode($jsonText);
        $schema = $this->registry->schemaFor($type);

        $this->rules->validate($decoded, $schema['fields']);

        return new ValidatedAiProposal($type, $schema['version'], $decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $jsonText): array
    {
        $candidate = trim($jsonText);

        // Tolerate the common "```json … ```" code fence wrapping.
        $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate);
        $candidate = preg_replace('/\s*```$/', '', $candidate);

        try {
            $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw AiOutputException::invalid('AI returned malformed JSON.');
        }

        if (! is_array($decoded)) {
            throw AiOutputException::invalid('AI output must be a JSON object.');
        }

        return $decoded;
    }
}
