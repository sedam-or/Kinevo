<?php

namespace App\Application\Scheduling;

use App\Application\Boosts\GetEffectiveTargetUseCase;
use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\DraftInput;
use App\Domain\Scheduling\Resolution\EffectiveLandscapeResolver;
use App\Domain\Scheduling\Resolution\HardLandscapeOccurrence;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Contracts\TaskRepository;
use Carbon\CarbonImmutable;

/**
 * ADR-016 — single assembly point for scheduler input (draft generator,
 * rescheduler, weekly prepare, Sync Now). Consumes the ADR-015 Effective
 * Landscape (resolved occurrences, never raw rows), accepted placements with
 * lock state, and the user's Sacred Anchor (ADR-016 §2.10).
 *
 * @phpstan-type AssembledScheduleInput array{
 *     input: DraftInput,
 *     base_version: ScheduleVersion,
 *     slots_by_task: array<string, TimeRange>,
 * }
 */
final readonly class AssembleScheduleInput
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private ScheduleOverrideRepository $overrides,
        private EffectiveLandscapeResolver $landscapeResolver,
        private TaskRepository $tasks,
        private GoalRepository $goals,
        private MilestoneRepository $milestones,
        private GetEffectiveTargetUseCase $effectiveBoostTarget,
    ) {}

    /**
     * @return AssembledScheduleInput
     */
    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $horizon = new TimeRange($from->startOfDay(), $to->endOfDay());
        $baseVersion = $this->assignments->currentScheduleVersion($userId);

        // Effective Hard Landscape (ADR-015): the deterministic scheduler
        // consumes RESOLVED occurrences — recurring series expanded into the
        // horizon, overrides applied — never raw source rows.
        $resolution = $this->landscapeResolver->resolve(
            $this->hardLandscape->listForUser($userId),
            $this->overrides->listForUser($userId),
            $horizon->start,
            $horizon->end,
        );

        $hardLandscape = array_map(
            static fn (HardLandscapeOccurrence $occurrence) => $occurrence->timeRange(),
            $resolution->occurrences,
        );

        /** @var array<string, TimeRange> $slotsByTask */
        $slotsByTask = [];
        $lockedByTask = [];

        foreach ($this->assignments->listForUserInRange($userId, $from, $to->endOfDay()) as $assignment) {
            $slotsByTask[(string) $assignment->taskId] = $assignment->timeRange();

            // ADR-015 locked-task contract: a locked placement is a
            // user-fixed placement — its lock state must reach the
            // scheduler/rescheduler input so automation can never move it.
            if ($assignment->locked) {
                $lockedByTask[(string) $assignment->taskId] = true;
            }
        }

        $tasks = [];
        $sacredAnchor = null;
        foreach ($this->tasks->listForUser($userId) as $task) {
            if ($task->status->isTerminal()) {
                continue;
            }

            $scheduleTask = new ScheduleTask(
                taskId: (string) $task->id,
                title: $task->title,
                durationMinutes: $task->estimatedMinutes ?? 45,
                priorityTier: new PriorityTier($task->priorityTier),
                goalDeadline: $this->goalDeadline($userId, $task->goalId),
                milestoneDeadline: $this->milestoneDeadline($userId, $task->milestoneId),
                taskDeadline: $task->dueAt,
                progress: $task->progress,
                isLocked: $lockedByTask[(string) $task->id] ?? false,
                isSacredAnchor: $task->isSacredAnchor,
                existingSlot: $slotsByTask[(string) $task->id] ?? null,
            );

            if ($scheduleTask->isSacredAnchor && $sacredAnchor === null) {
                // ADR-016 §2.10 — at most one anchor per user (task-level
                // validation); the generator places it first.
                $sacredAnchor = $scheduleTask;
            }

            $tasks[] = $scheduleTask;
        }

        return [
            'input' => new DraftInput(
                $horizon,
                hardLandscape: $hardLandscape,
                existingAssignments: array_values($slotsByTask),
                tasks: $tasks,
                sacredAnchor: $sacredAnchor,
                dailyCapacityPercent: $this->effectiveBoostTarget->__invoke($userId, $from)?->targetPercent,
            ),
            'base_version' => $baseVersion,
            'slots_by_task' => $slotsByTask,
        ];
    }

    private function goalDeadline(int $userId, ?int $goalId): ?CarbonImmutable
    {
        if ($goalId === null) {
            return null;
        }

        return $this->goals->findForUser($userId, $goalId)?->targetDate;
    }

    private function milestoneDeadline(int $userId, ?int $milestoneId): ?CarbonImmutable
    {
        if ($milestoneId === null) {
            return null;
        }

        return $this->milestones->findForUser($userId, $milestoneId)?->targetDate;
    }
}
