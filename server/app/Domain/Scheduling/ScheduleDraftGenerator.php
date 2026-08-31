<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\Deadline;
use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Deterministic auto-schedule draft engine (FR-27 weekly draft). Implements the
 * scheduling-engine core algorithm:
 *
 *   horizon → occupied intervals → Dynamic Empty Slots → Sacred Anchor →
 *   candidate set → hard constraints → ranking → greedy assignment → draft.
 *
 * Same inputs always produce the same draft (deterministic scheduling rule).
 * Persistence, run locks and Effective Capacity feedback are handled upstream.
 */
final class ScheduleDraftGenerator
{
    private const ANCHOR_EARLIEST_HOUR = 6;

    private const ANCHOR_DURATION_MINUTES = 25;

    public function __construct(
        private readonly SlotCalculator $slotCalculator,
        private readonly HardConstraintEngine $constraintEngine,
        private readonly TaskRankingEngine $rankingEngine,
    ) {}

    public function generate(DraftInput $input): ScheduleDraft
    {
        $days = $this->daysInHorizon($input->horizon);
        $assigned = [];
        $unassigned = [];
        $occupied = $input->existingAssignments;
        $placedIds = [];
        $capReasons = [];
        $ranked = $this->rankTasks($input->tasks);

        foreach ($ranked as $task) {
            if ($task->isLocked && $task->existingSlot !== null) {
                $assigned[] = new DraftAssignment($task->taskId, $task->title, $task->existingSlot);
                $placedIds[$task->taskId] = true;
            }
        }

        foreach ($days as $day) {
            $dayOccupied = array_merge($input->hardLandscape, $occupied);
            $slots = $this->slotCalculator->calculate($day, $dayOccupied);
            $dayScheduled = 0;
            $ceiling = $this->dailyCeiling($slots, $input->dailyCapacityPercent);

            if ($input->sacredAnchor !== null && ! isset($placedIds[$input->sacredAnchor->taskId])) {
                $anchorSlot = $this->findAnchorSlot($slots);
                if ($anchorSlot !== null && $this->fitsCap($ceiling, $dayScheduled, $input->sacredAnchor->durationMinutes)) {
                    $assignment = new DraftAssignment(
                        $input->sacredAnchor->taskId,
                        $input->sacredAnchor->title,
                        $anchorSlot,
                    );
                    $assigned[] = $assignment;
                    $occupied[] = $anchorSlot;
                    $placedIds[$input->sacredAnchor->taskId] = true;
                    $dayScheduled += $anchorSlot->durationMinutes()->value();
                }
            }

            foreach ($ranked as $task) {
                if (isset($placedIds[$task->taskId])) {
                    continue;
                }

                if (! $this->fitsCap($ceiling, $dayScheduled, $task->durationMinutes)) {
                    $capReasons[$task->taskId] = 'CAPACITY_CAP';

                    continue;
                }

                $slot = $this->findFittingSlot($task, $day, $slots, $input, $occupied);
                if ($slot === null) {
                    continue;
                }

                $assigned[] = new DraftAssignment($task->taskId, $task->title, $slot);
                $occupied[] = $slot;
                $placedIds[$task->taskId] = true;
                $dayScheduled += $slot->durationMinutes()->value();
            }

            if (count($placedIds) === count($input->tasks) + ($input->sacredAnchor === null ? 0 : 1)) {
                break;
            }
        }

        foreach ($input->tasks as $task) {
            if (! isset($placedIds[$task->taskId])) {
                $unassigned[] = new UnassignedTask(
                    $task->taskId,
                    $task->title,
                    $capReasons[$task->taskId] ?? 'NO_AVAILABLE_SLOT',
                );
            }
        }

        if ($input->sacredAnchor !== null && ! isset($placedIds[$input->sacredAnchor->taskId])) {
            $unassigned[] = new UnassignedTask(
                $input->sacredAnchor->taskId,
                $input->sacredAnchor->title,
                'NO_AVAILABLE_ANCHOR_SLOT',
            );
        }

        return new ScheduleDraft($assigned, $unassigned);
    }

    /**
     * Daily capacity ceiling in minutes when a boost target is active
     * (SRS FR-38: constrain to the configured percentage of daily capacity).
     * Reuses the day's Dynamic Empty Slots as the capacity baseline.
     *
     * @param  array<int, TimeRange>  $slots
     */
    private function dailyCeiling(array $slots, ?int $percent): ?int
    {
        if ($percent === null) {
            return null;
        }

        $available = array_sum(array_map(
            static fn (TimeRange $slot) => $slot->durationMinutes()->value(),
            $slots,
        ));

        return (int) floor($available * $percent / 100);
    }

    private function fitsCap(?int $ceiling, int $dayScheduled, int $durationMinutes): bool
    {
        return $ceiling === null || ($dayScheduled + $durationMinutes) <= $ceiling;
    }

    /**
     * @return array<int, TimeRange>
     */
    private function daysInHorizon(TimeRange $horizon): array
    {
        $days = [];
        $cursor = $horizon->start->startOfDay();

        while ($cursor->lessThan($horizon->end)) {
            $dayStart = $cursor;
            $dayEnd = $cursor->addDay();

            if ($dayEnd->greaterThan($horizon->end)) {
                $dayEnd = $horizon->end;
            }

            if ($dayStart->lessThan($horizon->end)) {
                $days[] = new TimeRange(
                    $dayStart->greaterThan($horizon->start) ? $dayStart : $horizon->start,
                    $dayEnd,
                );
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * @param  array<int, ScheduleTask>  $tasks
     * @return array<int, ScheduleTask>
     */
    private function rankTasks(array $tasks): array
    {
        $rankingCandidates = array_map(
            fn (ScheduleTask $task) => new RankingCandidate(
                taskId: $task->taskId,
                priorityTier: $task->priorityTier,
                goalDeadline: $task->goalDeadline,
                milestoneDeadline: $task->milestoneDeadline,
                taskDeadline: $task->taskDeadline,
                progress: $task->progress,
                contextFit: $task->contextFit,
                fragmentationPenalty: $task->fragmentationPenalty,
                continuityPreference: $task->continuityPreference,
                estimatedMinutes: $task->durationMinutes,
            ),
            $tasks,
        );

        $ordered = $this->rankingEngine->rank($rankingCandidates);
        $byId = [];
        foreach ($tasks as $task) {
            $byId[$task->taskId] = $task;
        }

        return array_map(
            static fn (RankingCandidate $c) => $byId[$c->taskId],
            $ordered,
        );
    }

    /**
     * First qualifying anchor slot: at least ANCHOR_DURATION_MINUTES long and
     * startable at/after ANCHOR_EARLIEST_HOUR (06:00 local). A slot that
     * starts before 06:00 but contains 06:00 qualifies — the anchor is
     * clipped to 06:00 (SRS glossary: "first qualifying slot ≥ 25 min at/
     * after 06:00").
     *
     * @param  array<int, TimeRange>  $slots
     */
    private function findAnchorSlot(array $slots): ?TimeRange
    {
        foreach ($slots as $slot) {
            if ($slot->durationMinutes()->value() < self::ANCHOR_DURATION_MINUTES) {
                continue;
            }

            $earliest = $slot->start->hour >= self::ANCHOR_EARLIEST_HOUR
                ? $slot->start
                : $slot->start->setTime(self::ANCHOR_EARLIEST_HOUR, 0);

            if ($earliest->lt($slot->start)
                || $earliest->addMinutes(self::ANCHOR_DURATION_MINUTES)->gt($slot->end)) {
                continue;
            }

            return new TimeRange($earliest, $earliest->addMinutes(self::ANCHOR_DURATION_MINUTES));
        }

        return null;
    }

    /**
     * @param  array<int, TimeRange>  $slots
     * @param  array<int, TimeRange>  $occupied
     */
    private function findFittingSlot(
        ScheduleTask $task,
        TimeRange $day,
        array $slots,
        DraftInput $input,
        array $occupied,
    ): ?TimeRange {
        foreach ($slots as $slot) {
            if ($slot->durationMinutes()->value() < $task->durationMinutes) {
                continue;
            }

            $candidate = new CandidatePlacement(
                taskId: $task->taskId,
                title: $task->title,
                durationMinutes: $task->durationMinutes,
                slot: new TimeRange($slot->start, $slot->start->addMinutes($task->durationMinutes)),
                deadline: $task->taskDeadline !== null
                    ? new Deadline($task->taskDeadline)
                    : null,
                isLocked: $task->isLocked,
                isSacredAnchor: $task->isSacredAnchor,
                priorityTier: $task->priorityTier,
            );

            $context = new ScheduleContext(
                $input->horizon,
                $input->hardLandscape,
                $occupied,
                reservePercent: $input->reservePercent,
            );

            if ($this->constraintEngine->isFeasible($context, [$candidate])) {
                return $candidate->slot;
            }
        }

        return null;
    }
}
