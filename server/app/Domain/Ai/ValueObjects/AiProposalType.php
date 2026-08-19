<?php

namespace App\Domain\Ai\ValueObjects;

use InvalidArgumentException;

/**
 * Versioned structured proposal categories (SRS §13.3). Each type maps to an
 * AI role and carries a schema version; the schema registry owns the rules.
 */
final class AiProposalType
{
    public const GOAL_BREAKDOWN = 'goal_breakdown';

    public const MILESTONE = 'milestone';

    public const TASK_EXTRACTION = 'task_extraction';

    public const CANVAS = 'canvas';

    public const SUMMARY = 'summary';

    public const TYPES = [
        self::GOAL_BREAKDOWN,
        self::MILESTONE,
        self::TASK_EXTRACTION,
        self::CANVAS,
        self::SUMMARY,
    ];

    private const ROLE_MAP = [
        self::GOAL_BREAKDOWN => AiRole::GOAL_DECOMPOSITION,
        self::MILESTONE => AiRole::MILESTONE_PROPOSAL,
        self::TASK_EXTRACTION => AiRole::TASK_EXTRACTION,
        self::CANVAS => AiRole::CANVAS_PROPOSAL,
        self::SUMMARY => AiRole::NOTE_SUMMARIZATION,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported AI proposal type: {$value}");
        }
    }

    public function role(): AiRole
    {
        return new AiRole(self::ROLE_MAP[$this->value]);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
