<?php

namespace App\Application\Scheduling;

use App\Domain\Goals\Contracts\GoalRepository;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Domain\Tasks\Contracts\TaskRepository;
use App\Domain\Tasks\Task;
use Carbon\CarbonImmutable;

/**
 * Read-model service for the schedule query endpoints (FR-01/FR-11/FR-15; SRS
 * §8.2). It composes an immutable view of the canonical schedule for a day,
 * range, week, or month — including task/program/goal/milestone context, lock
 * and conflict state, Hard Landscape boundaries, capacity indicators, and empty
 * (fillable) slots.
 *
 * This service is read-only and never mutates schedule state.
 */
final readonly class ScheduleQueryService
{
    public function __construct(
        private ScheduleAssignmentRepository $assignments,
        private HardLandscapeRepository $hardLandscape,
        private PauseEventRepository $pauseEvents,
        private TaskRepository $tasks,
        private GoalRepository $goals,
        private MilestoneRepository $milestones,
        private ProgramRepository $programs,
        private SlotCalculator $slots,
    ) {}

    /**
     * Build the canonical Today view for a single date (FR-01).
     *
     * @return array<string, mixed>
     */
    public function dayView(int $userId, CarbonImmutable $date): array
    {
        $assignments = array_values(array_filter(
            $this->assignments->listForUserOnDate($userId, $date),
            static fn (ScheduleAssignment $a) => ! $a->status->equals(
                ScheduleAssignmentStatus::cancelled(),
            ),
        ));

        $version = $this->assignments->currentScheduleVersion($userId);

        $events = array_map(
            fn (ScheduleAssignment $assignment) => $this->event($userId, $assignment, $assignments),
            $assignments,
        );

        // Hard Landscape boundaries on this day (FR-01/FR-27/FR-28).
        $landscape = $this->hardLandscape->listForUserOnDate($userId, $date);

        // Dynamic Empty Slots (FR-02): free intervals between occupied events and
        // Hard Landscape, excluding gaps shorter than the minimum fillable duration.
        $occupied = array_merge(
            array_map(static fn (ScheduleAssignment $a) => $a->timeRange(), $assignments),
            array_map(static fn (HardLandscapeEvent $e) => $e->timeRange(), $landscape),
        );
        $emptySlots = $this->slots->calculate($this->dayRange($date), $occupied);

        $scheduledMinutes = array_sum(array_map(
            static fn (ScheduleAssignment $a) => $a->durationMinutes,
            $assignments,
        ));
        $availableMinutes = array_sum(array_map(
            static fn (TimeRange $slot) => $slot->durationMinutes()->value(),
            $emptySlots,
        ));
        $overloadMinutes = max(0, $scheduledMinutes - $availableMinutes);

        return [
            'date' => $date->toDateString(),
            'schedule_version' => $version->value,
            'pause' => $this->pauseInfo($userId, $date),
            'events' => $events,
            'empty_slots' => array_map(
                static fn (TimeRange $slot) => [
                    'start' => $slot->start->toISOString(),
                    'end' => $slot->end->toISOString(),
                    'duration_minutes' => $slot->durationMinutes()->value(),
                ],
                $emptySlots,
            ),
            'hard_landscape' => array_map(
                static fn (HardLandscapeEvent $e) => $e->toArray(),
                $landscape,
            ),
            'capacity' => [
                'scheduled_minutes' => $scheduledMinutes,
                'available_minutes' => $availableMinutes,
                'overload_minutes' => $overloadMinutes,
                'status' => $overloadMinutes > 0 ? 'overload' : 'ok',
            ],
        ];
    }

    /**
     * Build a schedule view for an arbitrary date range (FR-01 navigation).
     *
     * @return array<string, mixed>
     */
    public function rangeView(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        // The API exposes date boundaries; treat `to` as inclusive of the full
        // `to` day (half-open [from, end-of-to-day) interval).
        $assignments = $this->assignments->listForUserInRange($userId, $from, $to->endOfDay());

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'schedule_version' => $this->assignments->currentScheduleVersion($userId)->value,
            'events' => array_map(
                fn (ScheduleAssignment $assignment) => $this->event(
                    $userId,
                    $assignment,
                    $this->assignments->listForUserOnDate($userId, $assignment->date),
                ),
                $assignments,
            ),
        ];
    }

    /**
     * Build a 7-day week view (FR-11).
     *
     * @return array<string, mixed>
     */
    public function weekView(int $userId, CarbonImmutable $date): array
    {
        $start = $date->startOfWeek();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->addDays($i);
            $dayAssignments = $this->assignments->listForUserOnDate($userId, $day);

            $days[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->dayOfWeekIso,
                'task_count' => count($dayAssignments),
                'scheduled_minutes' => array_sum(array_map(
                    static fn (ScheduleAssignment $a) => $a->durationMinutes,
                    $dayAssignments,
                )),
            ];
        }

        return [
            'start' => $start->toDateString(),
            'end' => $start->addDays(6)->toDateString(),
            'schedule_version' => $this->assignments->currentScheduleVersion($userId)->value,
            'pause' => $this->pauseInfo($userId, $start),
            'days' => $days,
        ];
    }

    /**
     * Build a monthly calendar summary (FR-15): one entry per calendar day.
     *
     * @return array<string, mixed>
     */
    public function monthView(int $userId, int $year, int $month): array
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $last = $first->endOfMonth();

        $assignments = $this->assignments->listForUserInRange($userId, $first, $last);
        $byDay = [];

        foreach ($assignments as $assignment) {
            $key = $assignment->date->toDateString();
            $byDay[$key] ??= ['task_count' => 0, 'scheduled_minutes' => 0];
            $byDay[$key]['task_count']++;
            $byDay[$key]['scheduled_minutes'] += $assignment->durationMinutes;
        }

        $days = [];
        for ($d = $first; $d->lte($last); $d = $d->addDay()) {
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $d->day,
                'task_count' => $byDay[$key]['task_count'] ?? 0,
                'scheduled_minutes' => $byDay[$key]['scheduled_minutes'] ?? 0,
            ];
        }

        return [
            'year' => $year,
            'month' => $month,
            'schedule_version' => $this->assignments->currentScheduleVersion($userId)->value,
            'days' => $days,
        ];
    }

    /**
     * Compose a single event entry with task + program/goal/milestone context,
     * lock state, and conflict state (overlap with another assignment).
     *
     * @param  array<int, ScheduleAssignment>  $dayAssignments
     * @return array<string, mixed>
     */
    private function event(int $userId, ScheduleAssignment $assignment, array $dayAssignments): array
    {
        $task = $this->tasks->findForUser($userId, $assignment->taskId);

        $conflict = false;
        foreach ($dayAssignments as $other) {
            if ($other->id !== $assignment->id && $assignment->overlapsWith($other)) {
                $conflict = true;
                break;
            }
        }

        return [
            'assignment' => $assignment->toArray(),
            'locked' => $assignment->locked,
            'conflict' => $conflict,
            'task' => $task !== null ? $task->toArray() : null,
            'program' => $this->programContext($userId, $task),
            'goal' => $this->goalContext($userId, $task),
            'milestone' => $this->milestoneContext($userId, $task),
        ];
    }

    private function dayRange(CarbonImmutable $date): TimeRange
    {
        return new TimeRange(
            $date->startOfDay(),
            $date->endOfDay(),
        );
    }

    /**
     * Exceptional-period state (FR-07): the active emergency pause tagging the
     * week containing the date, or null. Drives recovery-state visualization
     * and analytics "grey" for exceptional weeks.
     *
     * @return array<string, mixed>|null
     */
    private function pauseInfo(int $userId, CarbonImmutable $date): ?array
    {
        $pause = $this->pauseEvents->findEmergencyForWeek($userId, $date);

        if ($pause === null) {
            return null;
        }

        return [
            'type' => $pause->type->value,
            'week_start' => $pause->weekStart->toDateString(),
            'week_end' => $pause->weekEnd->toDateString(),
            'keep_task_ids' => array_map('strval', $pause->keepTaskIds),
            'moved_task_ids' => array_map('strval', $pause->movedTaskIds),
            'conflict_task_ids' => array_map('strval', $pause->conflictTaskIds),
            'schedule_version' => $pause->scheduleVersion,
        ];
    }

    private function programContext(int $userId, ?Task $task): ?array
    {
        if ($task === null || $task->programId === null) {
            return null;
        }

        $program = $this->programs->findForUser($userId, $task->programId);

        return $program !== null ? $program->toArray() : null;
    }

    private function goalContext(int $userId, ?Task $task): ?array
    {
        if ($task === null || $task->goalId === null) {
            return null;
        }

        $goal = $this->goals->findForUser($userId, $task->goalId);

        return $goal !== null ? $goal->toArray() : null;
    }

    private function milestoneContext(int $userId, ?Task $task): ?array
    {
        if ($task === null || $task->milestoneId === null) {
            return null;
        }

        $milestone = $this->milestones->findForUser($userId, $task->milestoneId);

        return $milestone !== null ? $milestone->toArray() : null;
    }
}
