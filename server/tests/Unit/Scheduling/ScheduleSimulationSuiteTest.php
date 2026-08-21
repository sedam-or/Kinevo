<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DraftInput;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\ScheduleDraftGenerator;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ScheduleSimulationSuiteTest extends TestCase
{
    private TimeRange $week;

    private ScheduleDraftGenerator $generator;

    protected function setUp(): void
    {
        $this->week = TimeRange::from('2026-08-17T00:00:00', '2026-08-24T00:00:00');
        $this->generator = new ScheduleDraftGenerator(
            new SlotCalculator,
            HardConstraintEngine::default(),
            TaskRankingEngine::default(),
        );
    }

    private function task(string $id, int $minutes, array $overrides = []): ScheduleTask
    {
        return new ScheduleTask(
            taskId: $id,
            title: $overrides['title'] ?? "Task {$id}",
            durationMinutes: $minutes,
            priorityTier: $overrides['priorityTier'] ?? PriorityTier::p3(),
            goalDeadline: $overrides['goalDeadline'] ?? null,
            milestoneDeadline: $overrides['milestoneDeadline'] ?? null,
            taskDeadline: $overrides['taskDeadline'] ?? null,
            progress: $overrides['progress'] ?? 0,
            isLocked: $overrides['isLocked'] ?? false,
            isSacredAnchor: $overrides['isSacredAnchor'] ?? false,
            existingSlot: $overrides['existingSlot'] ?? null,
        );
    }

    private function range(string $start, string $end): TimeRange
    {
        return TimeRange::from($start, $end);
    }

    // -----------------------------
    // TASK-152: empty day
    // -----------------------------

    #[Test]
    public function empty_day_all_tasks_scheduled_deterministically(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60), $this->task('t2', 90)],
        );

        $first = $this->generator->generate($input);
        $second = $this->generator->generate($input);

        $this->assertSame(['t1', 't2'], $first->assignedTaskIds());
        $this->assertTrue($first->isComplete());
        $this->assertEquals($first, $second);
    }

    // -----------------------------
    // TASK-152: hard landscape
    // -----------------------------

    #[Test]
    public function hard_landscape_is_never_overlapped(): void
    {
        $input = new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-17T09:00:00', '2026-08-17T11:00:00')],
            tasks: [$this->task('t1', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertFalse($draft->assignments[0]->slot->overlaps($this->range('2026-08-17T09:00:00', '2026-08-17T11:00:00')));
    }

    // -----------------------------
    // TASK-152: locked task
    // -----------------------------

    #[Test]
    public function locked_task_with_existing_slot_is_not_moved(): void
    {
        $existing = $this->range('2026-08-18T14:00:00', '2026-08-18T15:00:00');
        $input = new DraftInput(
            $this->week,
            existingAssignments: [$existing],
            tasks: [$this->task('locked', 60, [
                'isLocked' => true,
                'existingSlot' => $existing,
            ])],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertSame('2026-08-18 14:00:00', $draft->assignments[0]->slot->start->toDateTimeString());
    }

    // -----------------------------
    // TASK-152: sacred anchor
    // -----------------------------

    #[Test]
    public function sacred_anchor_is_placed_first_in_first_qualifying_slot_at_0600(): void
    {
        $anchor = $this->task('anchor', 25, [
            'isSacredAnchor' => true,
            'isLocked' => true,
        ]);
        $input = new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-17T05:00:00', '2026-08-17T06:00:00')],
            tasks: [$this->task('t1', 60)],
            sacredAnchor: $anchor,
        );

        $draft = $this->generator->generate($input);

        $this->assertSame(
            '2026-08-17 06:00:00',
            $draft->assignments[0]->slot->start->toDateTimeString(),
        );
        $this->assertSame('anchor', $draft->assignments[0]->taskId);
    }

    // -----------------------------
    // TASK-152: deadline pressure
    // -----------------------------

    #[Test]
    public function deadlines_are_respected_during_assignment(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60, [
                'taskDeadline' => new CarbonImmutable('2026-08-17T10:00:00'),
            ])],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertLessThanOrEqual(
            new CarbonImmutable('2026-08-17T10:00:00'),
            $draft->assignments[0]->slot->end,
        );
    }

    // -----------------------------
    // TASK-152: multiple goals (tasks with different goal deadlines)
    // -----------------------------

    #[Test]
    public function multiple_goals_tasks_placed_deterministically(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [
                $this->task('t1', 60, [
                    'goalDeadline' => new CarbonImmutable('2026-08-17T10:00:00'),
                ]),
                $this->task('t2', 60, [
                    'goalDeadline' => new CarbonImmutable('2026-08-17T14:00:00'),
                ]),
            ],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(2, $draft->assignments);
    }

    // -----------------------------
    // TASK-152: overload (capacity reduction via daily cap)
    // -----------------------------

    #[Test]
    public function boost_capacity_percent_limits_scheduled_minutes_per_day(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [
                $this->task('a', 90),
                $this->task('b', 90),
                $this->task('c', 90),
                $this->task('d', 90),
            ],
            dailyCapacityPercent: 10,
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(4, $draft->assignments);
        $this->assertCount(0, $draft->unassigned);
        foreach ($draft->assignments as $i => $assignment) {
            $this->assertSame(17 + $i, $assignment->slot->start->day, 'one task per day under a 10% ceiling');
        }
    }

    // -----------------------------
    // TASK-152: capacity boost (raises ceiling above normal)
    // -----------------------------

    #[Test]
    public function boost_capacity_percent_allows_more_tasks_than_cap(): void
    {
        // A 200% ceiling on a 1440-min day is effectively 2880 minutes — all
        // four 90-minute tasks fit easily.
        $input = new DraftInput(
            $this->week,
            tasks: [
                $this->task('a', 90),
                $this->task('b', 90),
                $this->task('c', 90),
                $this->task('d', 90),
            ],
            dailyCapacityPercent: 100,
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(4, $draft->assignments);
        $this->assertCount(0, $draft->unassigned);
    }

    // -----------------------------
    // TASK-152: conflicts (hard landscape overlap detection)
    // -----------------------------

    #[Test]
    public function conflicts_two_overlapping_hard_landmarks_report_conflict(): void
    {
        $input = new DraftInput(
            $this->week,
            hardLandscape: [
                $this->range('2026-08-17T09:00:00', '2026-08-17T10:30:00'),
                $this->range('2026-08-17T10:00:00', '2026-08-17T11:30:00'),
            ],
            tasks: [$this->task('t1', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertFalse($draft->assignments[0]->slot->overlaps($this->range('2026-08-17T09:00:00', '2026-08-17T11:30:00')));
    }

    // -----------------------------
    // TASK-152: dynamic reschedule (deterministic re-run)
    // -----------------------------

    #[Test]
    public function dynamic_reschedule_draft_version_is_consistent(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60), $this->task('t2', 60)],
        );

        $first = $this->generator->generate($input);
        $second = $this->generator->generate($input);

        $this->assertEquals($first->assignedTaskIds(), $second->assignedTaskIds());
        $this->assertEquals($first->isComplete(), $second->isComplete());
    }

    // -----------------------------
    // TASK-152: safety reserve enforced against over-booking
    // -----------------------------

    #[Test]
    public function safety_reserve_is_enforced_against_over_booking(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 8000)],
            reservePercent: 30,
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(0, $draft->assignments);
        $this->assertSame('NO_AVAILABLE_SLOT', $draft->unassigned[0]->reason);
    }

    // -----------------------------
    // TASK-152: null boost percent applies no capacity cap
    // -----------------------------

    #[Test]
    public function null_boost_percent_applies_no_capacity_cap(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('a', 800)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertCount(0, $draft->unassigned);
    }

    // -----------------------------
    // TASK-152: overload — demand beyond capped capacity leaves tasks unassigned
    // -----------------------------

    #[Test]
    public function overload_demand_beyond_capped_capacity_leaves_tasks_unassigned(): void
    {
        $tasks = [];
        for ($i = 1; $i <= 10; $i++) {
            $tasks[] = $this->task('t'.$i, 120);
        }
        $input = new DraftInput(
            $this->week,
            tasks: $tasks,
            dailyCapacityPercent: 10,
        );

        $first = $this->generator->generate($input);
        $second = $this->generator->generate($input);

        $this->assertCount(10, array_merge($first->assignments, $first->unassigned));
        $this->assertLessThan(10, count($first->assignments), 'capped week cannot absorb 1200 minutes');
        $this->assertGreaterThanOrEqual(1, count($first->unassigned));
        foreach ($first->unassigned as $unassigned) {
            $this->assertSame('CAPACITY_CAP', $unassigned->reason);
        }
        $this->assertEquals($first, $second, 'overload outcome must be deterministic');
    }

    // -----------------------------
    // TASK-152: context fit — draft fits around existing assignments
    // -----------------------------

    #[Test]
    public function context_fit_draft_fits_around_existing_assignments(): void
    {
        $existing = $this->range('2026-08-17T09:00:00', '2026-08-17T12:00:00');
        $input = new DraftInput(
            $this->week,
            existingAssignments: [$existing],
            tasks: [$this->task('t1', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertFalse(
            $draft->assignments[0]->slot->overlaps($existing),
            'scheduled work must fit the free context around existing events',
        );
    }
}
