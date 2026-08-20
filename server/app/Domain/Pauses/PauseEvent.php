<?php

namespace App\Domain\Pauses;

use App\Domain\Pauses\ValueObjects\PauseEventType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pause event (SRS §7.1 `pause_events`; FR-07). Records an Emergency/Mini Pause:
 * the exceptional period (week range for emergency), the tasks the user kept in
 * place, the tasks that were moved, and any conflicts that stayed visible.
 *
 * Emergency Pause never deletes tasks or rewrites historical activity; it only
 * tags the week and records which tasks moved. One emergency pause exists per
 * user/week (idempotent tagging).
 */
final class PauseEvent
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly PauseEventType $type,
        public readonly CarbonImmutable $weekStart,
        public readonly CarbonImmutable $weekEnd,
        public readonly array $keepTaskIds,
        public readonly array $movedTaskIds,
        public readonly array $conflictTaskIds,
        public readonly int $scheduleVersion,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Pause event user_id must be positive.');
        }

        if ($this->scheduleVersion <= 0) {
            throw new InvalidArgumentException('Pause event schedule_version must be positive.');
        }

        if ($this->weekEnd->lt($this->weekStart)) {
            throw new InvalidArgumentException('Pause event week_end cannot precede week_start.');
        }
    }

    public static function create(
        int $userId,
        PauseEventType $type,
        CarbonImmutable|string $weekStart,
        CarbonImmutable|string $weekEnd,
        array $keepTaskIds = [],
        array $movedTaskIds = [],
        array $conflictTaskIds = [],
        int $scheduleVersion = 1,
    ): self {
        return new self(
            null,
            $userId,
            $type,
            $weekStart instanceof CarbonImmutable ? $weekStart : CarbonImmutable::parse($weekStart),
            $weekEnd instanceof CarbonImmutable ? $weekEnd : CarbonImmutable::parse($weekEnd),
            $keepTaskIds,
            $movedTaskIds,
            $conflictTaskIds,
            $scheduleVersion,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->type,
            $this->weekStart,
            $this->weekEnd,
            $this->keepTaskIds,
            $this->movedTaskIds,
            $this->conflictTaskIds,
            $this->scheduleVersion,
            $this->createdAt,
        );
    }

    public function isEmergency(): bool
    {
        return $this->type->equals(PauseEventType::emergency());
    }

    /**
     * Whether a given date falls inside this pause event's week range.
     */
    public function covers(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return $day->gte($this->weekStart->startOfDay())
            && $day->lte($this->weekEnd->endOfDay());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type->value,
            'week_start' => $this->weekStart->toDateString(),
            'week_end' => $this->weekEnd->toDateString(),
            'keep_task_ids' => array_map('strval', $this->keepTaskIds),
            'moved_task_ids' => array_map('strval', $this->movedTaskIds),
            'conflict_task_ids' => array_map('strval', $this->conflictTaskIds),
            'schedule_version' => $this->scheduleVersion,
            'created_at' => $this->createdAt?->toISOString(),
        ];
    }
}
