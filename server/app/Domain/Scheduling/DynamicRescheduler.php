<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\Deadline;
use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Dynamic Rescheduler (FR-28). When Hard Landscape or a relevant constraint
 * changes, it generates a candidate plan via the deterministic draft engine,
 * computes the diff against the current schedule, and requires explicit
 * Apply/Cancel. Locked tasks are never moved by automation.
 */
final class DynamicRescheduler
{
    public function __construct(
        private readonly ScheduleDraftGenerator $draftGenerator,
        private readonly HardConstraintEngine $constraintEngine,
    ) {}

    /**
     * Produce a preview proposal without mutating any schedule. Only tasks whose
     * current slot became infeasible under the new constraints are moved.
     */
    public function propose(ScheduleState $current, DraftInput $input): RescheduleProposal
    {
        $draft = $this->draftGenerator->generate($input);

        $newSlots = [];
        foreach ($draft->assignments as $assignment) {
            $newSlots[$assignment->taskId] = $assignment->slot;
        }

        $moves = [];
        $conflicts = [];

        foreach ($input->tasks as $task) {
            $existing = $current->slotFor($task->taskId);

            if ($task->isLocked) {
                continue;
            }

            $existingStillFeasible = $existing !== null
                && $this->isSlotFeasible($task, $existing, $input, $current);

            if ($existing !== null && $existingStillFeasible) {
                continue;
            }

            $proposed = $newSlots[$task->taskId] ?? null;

            if ($proposed !== null && ($existing === null || ! $existing->equals($proposed))) {
                $moves[] = new TaskMove(
                    $task->taskId,
                    $task->title,
                    $existing,
                    $proposed,
                );
            } elseif ($proposed === null && $existing !== null) {
                $conflicts[] = $task->taskId;
            }
        }

        return new RescheduleProposal(
            $current->version,
            $current->version->next(),
            $moves,
            $conflicts,
        );
    }

    private function isSlotFeasible(
        ScheduleTask $task,
        TimeRange $slot,
        DraftInput $input,
        ScheduleState $current,
    ): bool {
        $candidate = new CandidatePlacement(
            taskId: $task->taskId,
            title: $task->title,
            durationMinutes: $task->durationMinutes,
            slot: $slot,
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
            array_values(array_filter(
                $current->assignments,
                static fn (TimeRange $_, string $id) => $id !== $task->taskId,
                ARRAY_FILTER_USE_BOTH,
            )),
            reservePercent: $input->reservePercent,
        );

        return $this->constraintEngine->isFeasible($context, [$candidate]);
    }

    /**
     * Commit a proposal atomically. Fails with a version conflict when the
     * underlying schedule has moved on (FR-28 exception flow → 409).
     */
    public function apply(ScheduleState $current, RescheduleProposal $proposal): ScheduleState
    {
        if (! $current->version->equals($proposal->baseVersion)) {
            throw new ScheduleVersionConflict($proposal->baseVersion, $current->version);
        }

        $assignments = $proposal->resultingAssignments($current);

        return $current->withAssignments($assignments);
    }
}
