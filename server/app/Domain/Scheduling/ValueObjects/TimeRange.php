<?php

namespace App\Domain\Scheduling\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Half-open temporal interval `[start, end)` (FR-01/FR-02 boundary rule).
 * Used for Hard Landscape, occupied events, and Dynamic Empty Slots.
 */
final class TimeRange
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if (! $end->greaterThan($start)) {
            throw new InvalidArgumentException('TimeRange end must be after start.');
        }
    }

    public static function from(
        CarbonImmutable|string $start,
        CarbonImmutable|string $end,
    ): self {
        return new self(
            $start instanceof CarbonImmutable ? $start : CarbonImmutable::parse($start),
            $end instanceof CarbonImmutable ? $end : CarbonImmutable::parse($end),
        );
    }

    public function durationMinutes(): DurationMinutes
    {
        return new DurationMinutes((int) $this->start->diffInMinutes($this->end));
    }

    /**
     * True when this interval overlaps another, including sharing a boundary.
     * `[10:00,11:00)` vs `[11:00,12:00)` do NOT overlap (half-open semantics).
     */
    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end)
            && $other->start->lessThan($this->end);
    }

    /**
     * True when this interval overlaps or directly touches another (merge-able).
     */
    public function overlapsOrAdjacent(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->end)
            && $other->start->lessThanOrEqualTo($this->end);
    }

    /**
     * Merge this interval with another overlapping/adjacent one.
     */
    public function merge(self $other): self
    {
        if (! $this->overlapsOrAdjacent($other)) {
            throw new InvalidArgumentException('Cannot merge disjoint TimeRanges.');
        }

        $start = $this->start->lessThan($other->start) ? $this->start : $other->start;
        $end = $this->end->greaterThan($other->end) ? $this->end : $other->end;

        return new self($start, $end);
    }

    /**
     * True when this range fully contains the given range.
     */
    public function contains(self $other): bool
    {
        return $this->start->lessThanOrEqualTo($other->start)
            && $this->end->greaterThanOrEqualTo($other->end);
    }

    /**
     * True when the given instant falls inside `[start, end)`.
     */
    public function containsInstant(CarbonImmutable $instant): bool
    {
        return $instant->greaterThanOrEqualTo($this->start)
            && $instant->lessThan($this->end);
    }

    public function equals(self $other): bool
    {
        return $this->start->equalTo($other->start)
            && $this->end->equalTo($other->end);
    }

    /**
     * @return array{start: string, end: string, duration_minutes: int}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start->toISOString(),
            'end' => $this->end->toISOString(),
            'duration_minutes' => $this->durationMinutes()->value(),
        ];
    }
}
