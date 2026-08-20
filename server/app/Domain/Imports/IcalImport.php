<?php

namespace App\Domain\Imports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A staged iCalendar (.ics) import (FR-30). Holds parsed VEVENT rows in a
 * pending staging state; rows are only persisted to Hard Landscape after
 * explicit user confirmation. Malformed events surface as per-event errors and
 * intentionally-skipped events as warnings — the user always sees a report
 * before anything is persisted (FR-30 Exception Flow; TASK-144).
 */
final class IcalImport
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
     * @param  array<int, array<string, mixed>>  $errors  per-event parse errors (FR-30 Exception Flow)
     * @param  array<int, array<string, mixed>>  $warnings  per-event warnings (TASK-144)
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $filename,
        public readonly string $status,
        public readonly ?float $confidence,
        public readonly array $rows,
        public readonly array $errors,
        public readonly array $warnings,
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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function confirmed(array $rows): self
    {
        return new self($this->id, $this->userId, $this->filename, self::STATUS_CONFIRMED, $this->confidence, $rows, $this->errors, $this->warnings, $this->createdAt);
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
