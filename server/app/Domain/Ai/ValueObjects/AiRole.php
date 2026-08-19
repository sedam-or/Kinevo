<?php

namespace App\Domain\Ai\ValueObjects;

use InvalidArgumentException;

/**
 * Allowed AI roles (docs/ai-architecture.md §AI roles). AI is optional
 * intelligence assistance; roles are fixed and domain-owned.
 */
final class AiRole
{
    public const GOAL_DECOMPOSITION = 'goal_decomposition';

    public const MILESTONE_PROPOSAL = 'milestone_proposal';

    public const NOTE_SUMMARIZATION = 'note_summarization';

    public const TASK_EXTRACTION = 'task_extraction';

    public const CONCEPT_EXTRACTION = 'concept_extraction';

    public const KNOWLEDGE_RELATION_SUGGESTION = 'knowledge_relation_suggestion';

    public const CANVAS_PROPOSAL = 'canvas_proposal';

    public const NATURAL_LANGUAGE_EXPLANATION = 'natural_language_explanation';

    private const ROLES = [
        self::GOAL_DECOMPOSITION,
        self::MILESTONE_PROPOSAL,
        self::NOTE_SUMMARIZATION,
        self::TASK_EXTRACTION,
        self::CONCEPT_EXTRACTION,
        self::KNOWLEDGE_RELATION_SUGGESTION,
        self::CANVAS_PROPOSAL,
        self::NATURAL_LANGUAGE_EXPLANATION,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::ROLES, true)) {
            throw new InvalidArgumentException("Unsupported AI role: {$value}");
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
