<?php

namespace App\Domain\Tasks\ValueObjects;

use InvalidArgumentException;

/**
 * Task lifecycle status (domain-model Task state machine, FR-09/FR-45/FR-48).
 * Transitions are explicit and validated. FR-48 Morning Recovery adds
 * missed → completed so a recovered task can be marked complete.
 */
final class TaskStatus
{
    public const BACKLOG = 'backlog';

    public const SCHEDULED = 'scheduled';

    public const IN_PROGRESS = 'in_progress';

    public const PARTIAL = 'partial';

    public const CONTINUED = 'continued';

    public const COMPLETED = 'completed';

    public const SKIPPED = 'skipped';

    public const MISSED = 'missed';

    public const CONFLICT = 'conflict';

    private const STATUSES = [
        self::BACKLOG,
        self::SCHEDULED,
        self::IN_PROGRESS,
        self::PARTIAL,
        self::CONTINUED,
        self::COMPLETED,
        self::SKIPPED,
        self::MISSED,
        self::CONFLICT,
    ];

    /** Explicit allowed transitions keyed by current status (domain-model state machine). */
    private const TRANSITIONS = [
        self::BACKLOG => [self::SCHEDULED, self::IN_PROGRESS, self::COMPLETED, self::SKIPPED],
        self::SCHEDULED => [self::IN_PROGRESS, self::MISSED, self::CONFLICT, self::SKIPPED, self::BACKLOG],
        self::IN_PROGRESS => [self::COMPLETED, self::PARTIAL, self::CONFLICT, self::SKIPPED],
        self::PARTIAL => [self::CONTINUED, self::COMPLETED, self::SCHEDULED, self::SKIPPED],
        self::CONTINUED => [self::SCHEDULED, self::IN_PROGRESS, self::COMPLETED, self::SKIPPED],
        self::MISSED => [self::BACKLOG, self::SCHEDULED, self::COMPLETED],
        self::CONFLICT => [self::SCHEDULED, self::IN_PROGRESS, self::BACKLOG],
        self::COMPLETED => [],
        self::SKIPPED => [],
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported task status: {$value}");
        }
    }

    public static function backlog(): self
    {
        return new self(self::BACKLOG);
    }

    public static function scheduled(): self
    {
        return new self(self::SCHEDULED);
    }

    public static function inProgress(): self
    {
        return new self(self::IN_PROGRESS);
    }

    public static function partial(): self
    {
        return new self(self::PARTIAL);
    }

    public static function continued(): self
    {
        return new self(self::CONTINUED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function skipped(): self
    {
        return new self(self::SKIPPED);
    }

    public static function missed(): self
    {
        return new self(self::MISSED);
    }

    public static function conflict(): self
    {
        return new self(self::CONFLICT);
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, self::TRANSITIONS[$this->value] ?? [], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->value, [self::COMPLETED, self::SKIPPED], true);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
