<?php

namespace App\Domain\Knowledge\ValueObjects;

use InvalidArgumentException;

/**
 * Domain object a knowledge item may link to (SRS §10.5). Canvases are linked
 * once the canvas aggregate ships (TASK-041).
 */
final class KnowledgeTargetType
{
    public const GOAL = 'goal';

    public const MILESTONE = 'milestone';

    public const PROGRAM = 'program';

    public const TASK = 'task';

    private const TYPES = [
        self::GOAL,
        self::MILESTONE,
        self::PROGRAM,
        self::TASK,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported knowledge target type: {$value}");
        }
    }

    public static function goal(): self
    {
        return new self(self::GOAL);
    }

    public static function milestone(): self
    {
        return new self(self::MILESTONE);
    }

    public static function program(): self
    {
        return new self(self::PROGRAM);
    }

    public static function task(): self
    {
        return new self(self::TASK);
    }

    public static function all(): array
    {
        return self::TYPES;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
