<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Hard Landscape Event — an external, non-negotiable schedule boundary (SRS
 * §7.1, `hard_landscape_events`). Automation MUST never overlap it
 * (scheduling-engine hard constraint #1; FR-27/FR-04).
 *
 * Immutable value semantics: state changes return a new instance. A
 * `permanent`/`recurring` event defines a recurring or standing boundary;
 * `one_time` is an explicit single block/override. Recurrence *generation* is
 * owned by TASK-096; this aggregate carries and validates the definition.
 */
final class HardLandscapeEvent
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly HardLandscapeType $type,
        public readonly CarbonImmutable $startAt,
        public readonly CarbonImmutable $endAt,
        public readonly ?string $recurrence = null,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Hard Landscape user_id must be positive.');
        }

        if (trim($this->title) === '') {
            throw new InvalidArgumentException('Hard Landscape title must not be empty.');
        }

        if (! $this->endAt->greaterThan($this->startAt)) {
            throw new InvalidArgumentException('Hard Landscape end_at must be after start_at.');
        }

        if ($this->type->equals(HardLandscapeType::recurring())
            && ($this->recurrence === null || trim($this->recurrence) === '')) {
            throw new InvalidArgumentException('Recurring Hard Landscape requires a recurrence rule.');
        }
    }

    public static function create(
        int $userId,
        string $title,
        HardLandscapeType $type,
        CarbonImmutable|string $startAt,
        CarbonImmutable|string $endAt,
        ?string $recurrence = null,
    ): self {
        return new self(
            0,
            $userId,
            trim($title),
            $type,
            $startAt instanceof CarbonImmutable ? $startAt : CarbonImmutable::parse($startAt),
            $endAt instanceof CarbonImmutable ? $endAt : CarbonImmutable::parse($endAt),
            $recurrence,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->title,
            $this->type,
            $this->startAt,
            $this->endAt,
            $this->recurrence,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function timeRange(): TimeRange
    {
        return new TimeRange($this->startAt, $this->endAt);
    }

    public function overlapsWith(self $other): bool
    {
        return $this->timeRange()->overlaps($other->timeRange());
    }

    public function occursOn(CarbonImmutable $date): bool
    {
        return $this->startAt->toDateString() === $date->toDateString()
            || $this->endAt->toDateString() === $date->toDateString()
            || ($this->startAt->lt($date->endOfDay()) && $this->endAt->gt($date->startOfDay()));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'type' => $this->type->value,
            'start_at' => $this->startAt->toISOString(),
            'end_at' => $this->endAt->toISOString(),
            'recurrence' => $this->recurrence,
            'created_at' => $this->createdAt?->toISOString(),
            'updated_at' => $this->updatedAt?->toISOString(),
        ];
    }
}
