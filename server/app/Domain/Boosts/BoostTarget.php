<?php

namespace App\Domain\Boosts;

use App\Domain\Boosts\ValueObjects\BoostTargetStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A confirmed holiday Boost target (SRS FR-37/FR-38). The user sets a target
 * percentage of normal daily capacity that the schedule may use during a
 * confirmed Break Mode period. Boost is scoped by start/end datetime and never
 * mutates the baseline target: when the validity period ends the scheduler
 * returns to the normal target (FR-38 Alternative Flow). The target is capped
 * at 70% capacity as a safety limit (FR-37 Business Rules).
 */
final class BoostTarget
{
    public const SAFETY_CAP_PERCENT = 70;

    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ?int $breakPeriodId,
        public readonly CarbonImmutable $startDate,
        public readonly CarbonImmutable $endDate,
        public readonly int $targetPercent,
        public readonly BoostTargetStatus $status,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Boost target user_id must be positive.');
        }

        if ($this->targetPercent < 1 || $this->targetPercent > 100) {
            throw new InvalidArgumentException('Boost target percent must be between 1 and 100.');
        }

        if ($this->endDate->lt($this->startDate)) {
            throw new InvalidArgumentException('Boost target end_date cannot precede start_date.');
        }
    }

    public static function create(
        int $userId,
        ?int $breakPeriodId,
        CarbonImmutable|string $startDate,
        CarbonImmutable|string $endDate,
        int $targetPercent,
    ): self {
        return new self(
            null,
            $userId,
            $breakPeriodId,
            $startDate instanceof CarbonImmutable ? $startDate : CarbonImmutable::parse($startDate),
            $endDate instanceof CarbonImmutable ? $endDate : CarbonImmutable::parse($endDate),
            $targetPercent,
            BoostTargetStatus::active(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->breakPeriodId,
            $this->startDate,
            $this->endDate,
            $this->targetPercent,
            $this->status,
            $this->createdAt,
        );
    }

    public function end(): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->breakPeriodId,
            $this->startDate,
            $this->endDate,
            $this->targetPercent,
            BoostTargetStatus::ended(),
            $this->createdAt,
        );
    }

    public function isActive(): bool
    {
        return $this->status->equals(BoostTargetStatus::active());
    }

    /**
     * Whether the target is active on the given date (scoped by start/end).
     */
    public function covers(CarbonImmutable $date): bool
    {
        $day = $date->startOfDay();

        return $day->gte($this->startDate->startOfDay())
            && $day->lte($this->endDate->endOfDay());
    }

    /**
     * Whether the proposed percent exceeds the 70% safety cap (FR-37).
     */
    public static function exceedsSafetyCap(int $targetPercent): bool
    {
        return $targetPercent > self::SAFETY_CAP_PERCENT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'break_period_id' => $this->breakPeriodId,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
            'target_percent' => $this->targetPercent,
            'status' => $this->status->value,
            'created_at' => $this->createdAt?->toISOString(),
        ];
    }
}
