<?php

namespace App\Domain\Scheduling\Resolution;

use App\Domain\Scheduling\ValueObjects\SchedulePrecedence;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Effective Landscape occurrence — one resolved Hard Landscape block inside a
 * requested window (ADR-015). Immutable value semantics.
 *
 * Canonical occurrence identity (ADR-015 Phase 2) is DERIVED, never persisted:
 *
 *     (source_event_id, original_occurrence_start)
 *
 * exposed via {@see identity()}. Occurrences are computed per request by
 * {@see EffectiveLandscapeResolver}; no occurrence storage exists.
 *
 * Current-v1 invariant: a recurring source produces at most ONE canonical
 * occurrence per local calendar date (`RecurrenceOccurrenceGenerator`
 * deduplicates by date). This preserves compatibility with the one_time
 * override date-targeting contract (`schedule_overrides.effective_from` date
 * == `effective_to` date). The resolver asserts the invariant on construction
 * of its result set rather than per object.
 */
final class HardLandscapeOccurrence
{
    public function __construct(
        public readonly int $sourceEventId,
        public readonly string $title,
        public readonly CarbonImmutable $originalStart,
        public readonly CarbonImmutable $originalEnd,
        public readonly CarbonImmutable $effectiveStart,
        public readonly CarbonImmutable $effectiveEnd,
        public readonly OccurrenceProvenance $provenance,
        public readonly SchedulePrecedence $precedence,
    ) {
        if ($this->sourceEventId <= 0) {
            throw new InvalidArgumentException('Occurrence source event id must be positive.');
        }

        if (trim($this->title) === '') {
            throw new InvalidArgumentException('Occurrence title must not be empty.');
        }

        if (! $this->originalEnd->greaterThan($this->originalStart)) {
            throw new InvalidArgumentException('Occurrence original_end must be after original_start.');
        }

        if (! $this->effectiveEnd->greaterThan($this->effectiveStart)) {
            throw new InvalidArgumentException('Occurrence effective_end must be after effective_start.');
        }
    }

    /**
     * A base occurrence: the effective window equals the original window.
     * Override resolution (ES-IMPL-04/05) will introduce shifted/excepted
     * constructors; until then the resolver only ever emits base occurrences.
     */
    public static function base(
        int $sourceEventId,
        string $title,
        CarbonImmutable $start,
        CarbonImmutable $end,
        SchedulePrecedence $precedence,
    ): self {
        return new self(
            $sourceEventId,
            $title,
            $start,
            $end,
            $start,
            $end,
            OccurrenceProvenance::base(),
            $precedence,
        );
    }

    /**
     * Canonical derived identity: `(source_event_id, original_occurrence_start)`.
     */
    public function identity(): string
    {
        return $this->sourceEventId.'|'.$this->originalStart->toISOString();
    }

    public function isBase(): bool
    {
        return $this->provenance->isBase();
    }

    /**
     * The effective (resolved) interval — the window other scheduling
     * consumers must respect.
     */
    public function timeRange(): TimeRange
    {
        return new TimeRange($this->effectiveStart, $this->effectiveEnd);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source_event_id' => $this->sourceEventId,
            'title' => $this->title,
            'original_start' => $this->originalStart->toISOString(),
            'original_end' => $this->originalEnd->toISOString(),
            'effective_start' => $this->effectiveStart->toISOString(),
            'effective_end' => $this->effectiveEnd->toISOString(),
            'provenance' => $this->provenance->value,
            'precedence' => $this->precedence->value,
        ];
    }
}
