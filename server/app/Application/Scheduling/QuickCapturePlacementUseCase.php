<?php

namespace App\Application\Scheduling;

use App\Application\Tasks\CreateTaskUseCase;
use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleContext;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * Quick Capture placement (FR-03): capture a task, attempt to place it into the
 * first feasible empty slot today, and persist an assignment on success.
 *
 * The task is always created and returned — a task never disappears. When no
 * slot fits, the result carries `TASK_NO_CAPACITY` and the three resolution
 * strategies (Manual Swap, Auto Swap, Schedule Later) for the UI.
 *
 * Slot feasibility reuses the hard-constraint engine (no Hard Landscape
 * collision, no illegal overlap, temporal validity, duration fit).
 */
final readonly class QuickCapturePlacementUseCase
{
    private const DEFAULT_DURATION_MINUTES = 45;

    private const SIZE_DURATION_MINUTES = [
        'cepat' => 15,
        'sedang' => 45,
        'berat' => 90,
    ];

    public function __construct(
        private CreateTaskUseCase $createTask,
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private SlotCalculator $slots,
        private HardConstraintEngine $constraintEngine,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        int $priorityTier,
        ?string $size = null,
        ?int $durationMinutes = null,
        ?int $programId = null,
        ?int $goalId = null,
        ?CarbonImmutable $date = null,
        mixed $workspaceId = null,
    ): QuickCaptureResult {
        // TASK-P19-024 — explicit parent context > active workspace.
        $duration = $this->resolveDuration($size, $durationMinutes);
        $task = $this->createTask->__invoke(
            $userId,
            $title,
            null,
            $programId,
            $goalId,
            null,
            $priorityTier,
            $duration,
            null,
            $workspaceId,
        );

        $targetDate = $date ?? CarbonImmutable::today();
        $slot = $this->findSlot($userId, $targetDate, $duration, (string) $task->id);

        if ($slot === null) {
            return QuickCaptureResult::noCapacity($task);
        }

        $assignment = $this->assignments->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $task->id,
            date: $slot->start,
            startAt: $slot->start,
            endAt: $slot->end,
            source: ScheduleAssignmentSource::quickCapture(),
            scheduleVersion: $this->assignments->currentScheduleVersion($userId)->value,
        ));

        return QuickCaptureResult::placed($task, $assignment);
    }

    private function resolveDuration(?string $size, ?int $durationMinutes): int
    {
        if ($durationMinutes !== null && $durationMinutes > 0) {
            return $durationMinutes;
        }

        if ($size !== null && isset(self::SIZE_DURATION_MINUTES[$size])) {
            return self::SIZE_DURATION_MINUTES[$size];
        }

        return self::DEFAULT_DURATION_MINUTES;
    }

    private function findSlot(int $userId, CarbonImmutable $date, int $duration, string $taskId): ?TimeRange
    {
        $existing = $this->assignments->listForUserOnDate($userId, $date);
        $landscape = $this->hardLandscape->listForUserOnDate($userId, $date);

        $assignmentRanges = array_map(
            static fn (ScheduleAssignment $a) => $a->timeRange(),
            $existing,
        );
        $landscapeRanges = array_map(
            static fn (HardLandscapeEvent $e) => $e->timeRange(),
            $landscape,
        );

        $occupied = array_merge($assignmentRanges, $landscapeRanges);
        $day = new TimeRange($date->startOfDay(), $date->endOfDay());
        $emptySlots = $this->slots->calculate($day, $occupied);

        foreach ($emptySlots as $emptySlot) {
            if ($emptySlot->durationMinutes()->value() < $duration) {
                continue;
            }

            $candidateSlot = new TimeRange($emptySlot->start, $emptySlot->start->addMinutes($duration));
            $candidate = new CandidatePlacement(
                taskId: $taskId,
                title: 'Quick Capture',
                durationMinutes: $duration,
                slot: $candidateSlot,
                priorityTier: new PriorityTier(3),
            );

            $context = new ScheduleContext($day, $landscapeRanges, $assignmentRanges);

            if ($this->constraintEngine->isFeasible($context, [$candidate])) {
                return $candidateSlot;
            }
        }

        return null;
    }
}
