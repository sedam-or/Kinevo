<?php

namespace App\Domain\Scheduling;

use InvalidArgumentException;

/**
 * A scheduler decision reason code (FR-63). Finite, domain-owned set so reason
 * output stays stable and machine-readable.
 */
final class ExplanationReason
{
    public const HARD_CONSTRAINT_FILTERED = 'HARD_CONSTRAINT_FILTERED';

    public const LOCK_PROTECTED = 'LOCK_PROTECTED';

    public const SACRED_ANCHOR = 'SACRED_ANCHOR';

    public const DEADLINE_PRIORITY = 'DEADLINE_PRIORITY';

    public const CAPACITY_FIT = 'CAPACITY_FIT';

    public const ENERGY_FIT = 'ENERGY_FIT';

    public const CONTEXT_SWITCH_PENALTY = 'CONTEXT_SWITCH_PENALTY';

    public const PROGRESS_VALUE = 'PROGRESS_VALUE';

    public const CONTINUITY_PREFERENCE = 'CONTINUITY_PREFERENCE';

    private const REASONS = [
        self::HARD_CONSTRAINT_FILTERED,
        self::LOCK_PROTECTED,
        self::SACRED_ANCHOR,
        self::DEADLINE_PRIORITY,
        self::CAPACITY_FIT,
        self::ENERGY_FIT,
        self::CONTEXT_SWITCH_PENALTY,
        self::PROGRESS_VALUE,
        self::CONTINUITY_PREFERENCE,
    ];

    private const LABELS = [
        self::HARD_CONSTRAINT_FILTERED => 'Alternatives were rejected by hard constraints',
        self::LOCK_PROTECTED => 'Locked task protected from automation',
        self::SACRED_ANCHOR => 'Sacred Anchor study commitment placed and locked',
        self::DEADLINE_PRIORITY => 'Nearest deadline prioritized',
        self::CAPACITY_FIT => 'Task fits available slot capacity',
        self::ENERGY_FIT => 'High energy/cognitive fit signal',
        self::CONTEXT_SWITCH_PENALTY => 'Context switch penalty considered',
        self::PROGRESS_VALUE => 'High progress leverage prioritized',
        self::CONTINUITY_PREFERENCE => 'Continuation of in-flight work preferred',
    ];

    public function __construct(
        public readonly string $code,
    ) {
        if (! in_array($code, self::REASONS, true)) {
            throw new InvalidArgumentException("Unsupported explanation reason: {$code}");
        }
    }

    public function label(): string
    {
        return self::LABELS[$this->code];
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code;
    }
}
