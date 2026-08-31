<?php

namespace App\Domain\OfflineSync\ValueObjects;

use InvalidArgumentException;

/**
 * ADR-017 §2.1 — closed allowlist of operation types the server will reconcile.
 * Dispatch is a closed switch over this set; nothing outside it is ever
 * executed through the reconciliation endpoint.
 */
final class OperationType
{
    public const TASK_CREATE = 'task:create';

    public const TASK_UPDATE = 'task:update';

    public const TASK_STATUS = 'task:status';

    public const SUBTASK_CREATE = 'subtask:create';

    public const NOTE_CREATE = 'note:create';

    public const NOTE_UPDATE = 'note:update';

    private const SUPPORTED = [
        self::TASK_CREATE,
        self::TASK_UPDATE,
        self::TASK_STATUS,
        self::SUBTASK_CREATE,
        self::NOTE_CREATE,
        self::NOTE_UPDATE,
    ];

    public function __construct(
        public readonly string $value,
    ) {
        if (! in_array($value, self::SUPPORTED, true)) {
            throw new InvalidArgumentException("Unsupported offline operation type: {$value}");
        }
    }

    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
