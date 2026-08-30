<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Schedule Override — a permanent shift or one-time exception applied to a
 * recurring schedule series (FR-25, SRS §7.1 `schedule_overrides`).
 *
 * - `permanent` deactivates the original recurring occurrence(s) across
 *   `[effectiveFrom, effectiveTo]` and replaces them with the override interval.
 * - `one_time` removes only a single occurrence (`effectiveFrom === effectiveTo`)
 *   and replaces it with the override interval.
 *
 * Overrides are additive and never rewrite the source series' history ("no
 * silent historical mutation"): the effective schedule is computed by resolving
 * precedence (see SchedulePrecedence), not by mutating the recurring source.
 *
 * Immutable value semantics: state changes return a new instance.
 */
final class ScheduleOverride
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $hardLandscapeEventId,
        public readonly ScheduleOverrideType $type,
        public readonly CarbonImmutable $effectiveFrom,
        public readonly CarbonImmutable $effectiveTo,
        public readonly CarbonImmutable $overrideStartAt,
        public readonly CarbonImmutable $overrideEndAt,
        public readonly ?string $reason = null,
        public readonly bool $cancelsOccurrence = false,
        public readonly ?CarbonImmutable $createdAt = null,
        public readonly ?CarbonImmutable $updatedAt = null,
    ) {
        if ($this->userId <= 0) {
            throw new InvalidArgumentException('Schedule override user_id must be positive.');
        }

        if ($this->hardLandscapeEventId <= 0) {
            throw new InvalidArgumentException('Schedule override source event id must be positive.');
        }

        if (! $this->overrideEndAt->greaterThan($this->overrideStartAt)) {
            throw new InvalidArgumentException('Schedule override end must be after its start.');
        }

        if ($this->effectiveTo->lt($this->effectiveFrom)) {
            throw new InvalidArgumentException('Schedule override effective_to cannot precede effective_from.');
        }

        if ($this->type->equals(ScheduleOverrideType::oneTime())
            && $this->effectiveFrom->toDateString() !== $this->effectiveTo->toDateString()) {
            throw new InvalidArgumentException('One-time override must target a single occurrence date.');
        }

        // Cancellation is an occurrence-level statement (ADR-015): it targets
        // exactly one occurrence and has no replacement interval semantics.
        if ($this->cancelsOccurrence && ! $this->type->equals(ScheduleOverrideType::oneTime())) {
            throw new InvalidArgumentException('Only a one-time override can cancel an occurrence.');
        }
    }

    public static function create(
        int $userId,
        int $hardLandscapeEventId,
        ScheduleOverrideType $type,
        CarbonImmutable|string $effectiveFrom,
        CarbonImmutable|string $effectiveTo,
        CarbonImmutable|string $overrideStartAt,
        CarbonImmutable|string $overrideEndAt,
        ?string $reason = null,
        bool $cancelsOccurrence = false,
    ): self {
        return new self(
            0,
            $userId,
            $hardLandscapeEventId,
            $type,
            $effectiveFrom instanceof CarbonImmutable ? $effectiveFrom : CarbonImmutable::parse($effectiveFrom),
            $effectiveTo instanceof CarbonImmutable ? $effectiveTo : CarbonImmutable::parse($effectiveTo),
            $overrideStartAt instanceof CarbonImmutable ? $overrideStartAt : CarbonImmutable::parse($overrideStartAt),
            $overrideEndAt instanceof CarbonImmutable ? $overrideEndAt : CarbonImmutable::parse($overrideEndAt),
            $reason,
            $cancelsOccurrence,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->hardLandscapeEventId,
            $this->type,
            $this->effectiveFrom,
            $this->effectiveTo,
            $this->overrideStartAt,
            $this->overrideEndAt,
            $this->reason,
            $this->cancelsOccurrence,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    public function overrideRange(): TimeRange
    {
        return new TimeRange($this->overrideStartAt, $this->overrideEndAt);
    }

    public function overlapsOverrideWith(self $other): bool
    {
        return $this->hardLandscapeEventId === $other->hardLandscapeEventId
            && $this->overrideRange()->overlaps($other->overrideRange());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'hard_landscape_event_id' => $this->hardLandscapeEventId,
            'type' => $this->type->value,
            'effective_from' => $this->effectiveFrom->toISOString(),
            'effective_to' => $this->effectiveTo->toISOString(),
            'override_start_at' => $this->overrideStartAt->toISOString(),
            'override_end_at' => $this->overrideEndAt->toISOString(),
            'reason' => $this->reason,
            'cancels_occurrence' => $this->cancelsOccurrence,
            'created_at' => $this->createdAt?->toISOString(),
            'updated_at' => $this->updatedAt?->toISOString(),
        ];
    }
}
