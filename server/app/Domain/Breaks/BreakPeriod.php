<?php

namespace App\Domain\Breaks;

use App\Domain\Breaks\ValueObjects\BreakPeriodStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Confirmed Break Mode period (SRS FR-36). A date range the user confirmed as a
 * break/holiday: the week(s) it covers are tagged exceptional so the capacity
 * feedback loop excludes them (FR-49) and EOD prompts are suppressed.
 *
 * Detection never activates a break without confirmation; the period only
 * exists in `active` state after the user confirms the manual date range.
 */
final class BreakPeriod
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly BreakPeriodStatus $status,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Break period user_id must be positive.');
        }

        if ($this->endDate->lt($this->startDate)) {
            throw new InvalidArgumentException('Break period end_date cannot precede start_date.');
        }
    }

    public static function create(
        int $userId,
        CarbonImmutable|string $startDate,
        CarbonImmutable|string $endDate,
    ): self {
        return new self(
            null,
            $userId,
            $startDate instanceof CarbonImmutable ? $startDate : CarbonImmutable::parse($startDate),
            $endDate instanceof CarbonImmutable ? $endDate : CarbonImmutable::parse($endDate),
            BreakPeriodStatus::active(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->startDate,
            $this->endDate,
            $this->status,
            $this->createdAt,
        );
    }

    public function end(?CarbonImmutable $endedAt = null): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->startDate,
            $this->endDate,
            BreakPeriodStatus::ended(),
            $this->createdAt,
        );
    }

    public function isActive(): bool
    {
        return $this->status->equals(BreakPeriodStatus::active());
    }

    /**
     * Whether a given date falls inside this break period.
     */
    public function covers(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return $day->gte($this->startDate->startOfDay())
            && $day->lte($this->endDate->endOfDay());
    }

    /**
     * Number of calendar days covered by the break period.
     */
    public function durationDays(): int
    {
        return (int) $this->startDate->startOfDay()->diffInDays($this->endDate->startOfDay()) + 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'status' => $this->status->value,
            'duration_days' => $this->durationDays(),
            'created_at' => $this->createdAt?->toISOString(),
        ];
    }
}
