<?php

namespace App\Domain\Imports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A staged KRS PDF import (FR-24). Holds parsed schedule rows in a pending
 * staging state; rows are only persisted to Hard Landscape after explicit user
 * confirmation. Never silently overwrites an existing schedule.
 *
 * TASK-144: per-line validation errors and warnings are staged alongside the
 * rows so the preview shows the full picture — invalid data is never imported
 * silently.
 */
final class KrsImport
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_DISCARDED = 'discarded';

    private const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_DISCARDED,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $errors  per-line parse errors (TASK-144)
     * @param  array<int, array<string, mixed>>  $warnings  per-row warnings (TASK-144)
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $filename,
        public readonly string $status,
        public readonly ?float $confidence,
        public readonly array $rows,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported import status: {$status}");
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $errors
     * @param  array<int, array<string, mixed>>  $warnings
     */
    public static function stage(
        int $userId,
        string $filename,
        ?float $confidence,
        array $rows,
        array $errors = [],
        array $warnings = [],
    ): self {
        return new self(
            null,
            $userId,
            $filename,
            self::STATUS_PENDING,
            $confidence,
            $rows,
            $errors,
            $warnings,
            CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self($id, $this->userId, $this->filename, $this->status, $this->confidence, $this->rows, $this->errors, $this->warnings, $this->createdAt);
    }

    public function confirmed(): self
    {
        return new self($this->id, $this->userId, $this->filename, self::STATUS_CONFIRMED, $this->confidence, $this->rows, $this->errors, $this->warnings, $this->createdAt);
    }

    public function discarded(): self
    {
        return new self($this->id, $this->userId, $this->filename, self::STATUS_DISCARDED, $this->confidence, $this->rows, $this->errors, $this->warnings, $this->createdAt);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'status' => $this->status,
            'confidence' => $this->confidence,
            'rows' => $this->rows,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'created_at' => $this->createdAt?->toIso8601String(),
        ];
    }
}
