<?php

namespace App\Domain\Scheduling\Resolution;

/**
 * Result of one Effective Landscape resolution (ADR-015): the resolved,
 * deterministically ordered occurrences for the window, plus diagnostics —
 * recurrence warnings and cancelled occurrences (pure values; no persistence
 * and no behavior).
 */
final class EffectiveLandscapeResolution
{
    /**
     * @param  list<HardLandscapeOccurrence>  $occurrences  ordered by effectiveStart, then sourceEventId, then originalStart
     * @param  list<RecurrenceResolutionWarning>  $recurrenceWarnings  one per recurring source whose rule failed to parse (degraded to base)
     * @param  list<HardLandscapeOccurrence>  $cancelledOccurrences  occurrences removed by a cancelling one-time override (diagnostics only; never part of the effective landscape)
     */
    public function __construct(
        public readonly array $occurrences,
        public readonly array $recurrenceWarnings = [],
        public readonly array $cancelledOccurrences = [],
    ) {}
}
