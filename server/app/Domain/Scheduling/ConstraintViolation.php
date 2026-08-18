<?php

namespace App\Domain\Scheduling;

/**
 * A hard-constraint violation (FR-64). Produced when a candidate placement
 * breaks a hard rule; such candidates are rejected before any soft scoring.
 */
final class ConstraintViolation
{
    public function __construct(
        public readonly string $ruleCode,
        public readonly string $taskId,
        public readonly string $message,
    ) {}

    public function equals(self $other): bool
    {
        return $this->ruleCode === $other->ruleCode
            && $this->taskId === $other->taskId
            && $this->message === $other->message;
    }
}
