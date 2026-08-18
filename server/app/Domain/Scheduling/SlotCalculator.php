<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Computes Dynamic Empty Slots (FR-02): free `[start,end)` intervals between
 * occupied intervals (Hard Landscape + non-overlapping events), excluding gaps
 * shorter than the minimum fillable duration. Overlapping occupied events are
 * merged and never treated as available time.
 *
 * Deterministic: same inputs → same slots.
 */
final class SlotCalculator
{
    public function __construct(
        private readonly int $minimumSlotMinutes = 15,
    ) {}

    /**
     * @param  array<int, TimeRange>  $occupied
     * @return array<int, TimeRange>
     */
    public function calculate(TimeRange $day, array $occupied): array
    {
        $merged = $this->mergeOccupied($occupied);

        $slots = [];
        $cursor = $day->start;

        foreach ($merged as $range) {
            if ($range->start->greaterThan($cursor)) {
                $gap = new TimeRange($cursor, $range->start);
                if ($gap->durationMinutes()->value() >= $this->minimumSlotMinutes) {
                    $slots[] = $gap;
                }
            }

            // Advance cursor past occupied time; overlapping input already merged.
            $cursor = $range->end->greaterThan($cursor) ? $range->end : $cursor;
        }

        if ($day->end->greaterThan($cursor)) {
            $tail = new TimeRange($cursor, $day->end);
            if ($tail->durationMinutes()->value() >= $this->minimumSlotMinutes) {
                $slots[] = $tail;
            }
        }

        return $slots;
    }

    /**
     * @param  array<int, TimeRange>  $occupied
     * @return array<int, TimeRange>
     */
    private function mergeOccupied(array $occupied): array
    {
        $sorted = $occupied;
        usort($sorted, static fn (TimeRange $a, TimeRange $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        $merged = [];
        foreach ($sorted as $range) {
            $last = end($merged);

            if ($last !== false && $last->overlapsOrAdjacent($range)) {
                $merged[array_key_last($merged)] = $last->merge($range);
            } else {
                $merged[] = $range;
            }
        }

        return $merged;
    }
}
