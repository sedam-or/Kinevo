<?php

namespace App\Domain\Workspaces\ValueObjects;

use InvalidArgumentException;

/**
 * Workspace lifecycle status (TASK-P19-001, TASK-P19-030).
 * Archive preserves all data and is always reversible (restore).
 */
final class WorkspaceStatus
{
    public const ACTIVE = 'active';

    public const ARCHIVED = 'archived';

    private function __construct(
        public readonly string $value,
    ) {}

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function archived(): self
    {
        return new self(self::ARCHIVED);
    }

    public static function from(string $value): self
    {
        if (! in_array($value, [self::ACTIVE, self::ARCHIVED], true)) {
            throw new InvalidArgumentException("Unsupported workspace status [{$value}].");
        }

        return new self($value);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->value === self::ARCHIVED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
