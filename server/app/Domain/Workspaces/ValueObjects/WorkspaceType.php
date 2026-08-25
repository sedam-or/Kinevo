<?php

namespace App\Domain\Workspaces\ValueObjects;

use InvalidArgumentException;

/**
 * Workspace type — a coarse intent label for a workspace family
 * (TASK-P19-001). Open set validated against known families; the default
 * family is `personal` (the auto-provisioned default workspace).
 */
final class WorkspaceType
{
    public const PERSONAL = 'personal';

    public const WORK = 'work';

    public const RESEARCH = 'research';

    public const LEARNING = 'learning';

    public const OTHER = 'other';

    private function __construct(
        public readonly string $value,
    ) {}

    /** @return array<int, string> */
    public static function allowed(): array
    {
        return [self::PERSONAL, self::WORK, self::RESEARCH, self::LEARNING, self::OTHER];
    }

    public static function from(?string $value): self
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return new self(self::PERSONAL);
        }

        if (! in_array($normalized, self::allowed(), true)) {
            throw new InvalidArgumentException("Unsupported workspace type [{$value}].");
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
