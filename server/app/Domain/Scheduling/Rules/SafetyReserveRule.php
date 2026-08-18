<?php

namespace App\Domain\Scheduling\Rules;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\ConstraintViolation;
use App\Domain\Scheduling\Contracts\HardConstraintRule;
use App\Domain\Scheduling\ScheduleContext;
use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Safety reserve (hard constraint #8, FR-27). The schedule must leave at least
 * `reservePercent`% of the horizon unoccupied (recharge/buffer reserve) —
 * occupied time is Hard Landscape + existing assignments + all candidates.
 */
final class SafetyReserveRule implements HardConstraintRule
{
    public function code(): string
    {
        return 'SAFETY_RESERVE';
    }

    public function violations(CandidatePlacement $candidate, ScheduleContext $context): array
    {
        $capacity = $context->horizon->durationMinutes()->value();
        $reserveLimit = (int) floor($capacity * (1 - $context->reservePercent / 100));

        $occupied = $context->hardLandscape;
        foreach ($context->existingAssignments as $assignment) {
            $occupied[] = $assignment;
        }
        foreach ($context->candidates as $other) {
            $occupied[] = $other->slot;
        }
        $occupied[] = $candidate->slot;

        $busyMinutes = $this->sumMergedMinutes($occupied);

        if ($busyMinutes > $reserveLimit) {
            return [new ConstraintViolation(
                $this->code(),
                $candidate->taskId,
                "Occupied time ({$busyMinutes} min) exceeds safety reserve limit ({$reserveLimit} min).",
            )];
        }

        return [];
    }

    /**
     * Sum of merged occupied intervals (overlaps counted once).
     *
     * @param  array<int, TimeRange>  $ranges
     */
    private function sumMergedMinutes(array $ranges): int
    {
        if ($ranges === []) {
            return 0;
        }

        usort($ranges, static fn (TimeRange $a, TimeRange $b) => $a->start->getTimestamp() <=> $b->start->getTimestamp());

        $total = 0;
        $cursor = $ranges[0]->start;
        $furthest = $ranges[0]->end;

        foreach ($ranges as $range) {
            if ($range->start->greaterThan($furthest)) {
                $total += (int) $furthest->diffInMinutes($range->start);
                $cursor = $range->start;
                $furthest = $range->end;

                continue;
            }
            if ($range->end->greaterThan($furthest)) {
                $furthest = $range->end;
            }
        }

        $total += (int) $cursor->diffInMinutes($furthest);

        return $total;
    }
}
