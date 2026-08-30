<?php

namespace App\Domain\Scheduling\Recurrence;

use Carbon\CarbonImmutable;

/**
 * Generates concrete occurrence datetimes from a RecurrenceRule within a
 * bounded window. Deterministic, timezone-aware, duplicate-free.
 *
 * Each occurrence keeps the time-of-day of the rule's DTSTART (in the start's
 * timezone), so a weekly event at 23:30 stays at 23:30 on each occurrence date
 * rather than drifting across a UTC day boundary.
 *
 * Boundedness:
 * - explicit `[from, to)` window;
 * - optional `maxOccurrences` guard (default 1000) so a misconfigured or
 *   far-future rule can never produce unbounded output;
 * - optional `excluded` dates (exceptions / cancelled or deleted occurrences)
 *   are skipped.
 */
final class RecurrenceOccurrenceGenerator
{
    public const DEFAULT_MAX_OCCURRENCES = 1000;

    /**
     * @param  array<int, CarbonImmutable>  $excluded  occurrence dates to skip
     * @return array<int, CarbonImmutable> sorted occurrence datetimes (unique by day)
     */
    public function generate(
        RecurrenceRule $rule,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $excluded = [],
        int $maxOccurrences = self::DEFAULT_MAX_OCCURRENCES,
    ): array {
        $tz = $rule->start()->timezone;
        $windowStart = $from->timezone($tz)->startOfDay();
        $windowEnd = $to->timezone($tz)->startOfDay();

        $excludedDays = [];
        foreach ($excluded as $ex) {
            $excludedDays[$ex->timezone($tz)->toDateString()] = true;
        }

        $occurrences = [];
        $seenMatches = 0;
        $until = $rule->until;

        // RFC-5545 COUNT counts occurrences from DTSTART (the series start),
        // never from the query window. Only series with a COUNT need the
        // pre-window scan; all other rules iterate the window directly.
        $cursor = $windowStart;
        if ($rule->count !== null) {
            $seriesStart = $rule->start()->startOfDay();
            if ($seriesStart->lessThan($windowStart)) {
                $cursor = $seriesStart;
            }
        }

        // Hard ceiling so even a huge window, an old series, or a
        // misconfigured rule stays bounded: the full scan span plus the
        // max-occurrence guard margin.
        $boundary = $windowEnd->addDay();
        $maxDays = (int) $cursor->startOfDay()->diffInDays($boundary)
            + $maxOccurrences * max(1, $rule->interval) * 7 + 1;

        while ($cursor->lt($boundary) && $maxDays-- > 0) {
            if ($rule->matches($cursor)) {
                $seenMatches++;

                // RFC-5545 COUNT bounds total occurrences from DTSTART.
                if ($rule->count !== null && $seenMatches > $rule->count) {
                    break;
                }

                $occurrence = $this->occurrenceOn($rule, $cursor);

                // RFC-5545 UNTIL is inclusive: date-only UNTIL includes its
                // local date; datetime UNTIL includes its exact instant (a
                // trailing Z is UTC, normalized by RecurrenceRule). COUNT and
                // UNTIL both apply — whichever terminates first wins.
                if ($until !== null) {
                    if ($rule->untilIsDateOnly) {
                        if ($occurrence->toDateString() > $until->toDateString()) {
                            break;
                        }
                    } elseif ($occurrence->gt($until)) {
                        break;
                    }
                }

                // When scanning from the series start (COUNT semantics),
                // only occurrences inside the requested window are emitted.
                if ($occurrence->greaterThanOrEqualTo($windowStart)
                    && ! isset($excludedDays[$occurrence->toDateString()])) {
                    $occurrences[$occurrence->toDateString()] = $occurrence;
                }
            }

            $cursor = $cursor->addDay();
        }

        // Deterministic, duplicate-free ordering by date, capped by the guard.
        ksort($occurrences);

        return array_slice(array_values($occurrences), 0, $maxOccurrences);
    }

    private function occurrenceOn(RecurrenceRule $rule, CarbonImmutable $day): CarbonImmutable
    {
        $start = $rule->start();

        return $day->setTime($start->hour, $start->minute, $start->second);
    }
}
