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

class ScheduleDraftGeneratorTest extends TestCase
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

    #[Test]
    public function empty_week_schedules_all_tasks_deterministically(): void
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

    #[Test]
    public function zero_hard_landscape_uses_full_day_capacity(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertSame('2026-08-17 00:00:00', $draft->assignments[0]->slot->start->toDateTimeString());
    }

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

    #[Test]
    public function adjacent_hard_landscape_blocks_leave_no_illegal_gap_placement(): void
    {
        $input = new DraftInput(
            $this->week,
            hardLandscape: [
                $this->range('2026-08-17T09:00:00', '2026-08-17T10:00:00'),
                $this->range('2026-08-17T10:00:00', '2026-08-17T11:00:00'),
            ],
            tasks: [$this->task('t1', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        foreach ($draft->assignments as $assignment) {
            $this->assertFalse(
                $assignment->slot->overlaps($this->range('2026-08-17T09:00:00', '2026-08-17T11:00:00'))
            );
        }
    }

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

    #[Test]
    public function higher_priority_task_is_assigned_before_lower_priority(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [
                $this->task('low', 300, ['priorityTier' => PriorityTier::p3()]),
                $this->task('high', 300, ['priorityTier' => PriorityTier::p1()]),
            ],
        );

        $draft = $this->generator->generate($input);

        $this->assertSame('high', $draft->assignments[0]->taskId);
    }

    #[Test]
    public function task_that_does_not_fit_any_slot_is_reported_unassigned(): void
    {
        $input = new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-17T00:00:00', '2026-08-24T00:00:00')],
            tasks: [$this->task('big', 60)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(0, $draft->assignments);
        $this->assertCount(1, $draft->unassigned);
        $this->assertSame('big', $draft->unassigned[0]->taskId);
        $this->assertSame('NO_AVAILABLE_SLOT', $draft->unassigned[0]->reason);
    }

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

    #[Test]
    public function multiple_equal_priority_tasks_assign_without_overlap(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [
                $this->task('a', 60),
                $this->task('b', 60),
                $this->task('c', 60),
            ],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(3, $draft->assignments);
        foreach ($draft->assignments as $i => $assignment) {
            foreach ($draft->assignments as $j => $other) {
                if ($i !== $j) {
                    $this->assertFalse($assignment->slot->overlaps($other->slot));
                }
            }
        }
    }

    #[Test]
    public function draft_is_deterministic_under_equal_priority_tie(): void
    {
        $input = new DraftInput(
            $this->week,
            tasks: [$this->task('a', 60), $this->task('b', 60)],
        );

        $first = $this->generator->generate($input);
        $second = $this->generator->generate($input);

        $this->assertEquals($first->assignedTaskIds(), $second->assignedTaskIds());
    }

    #[Test]
    public function boost_capacity_percent_limits_scheduled_minutes_per_day(): void
    {
        // A full empty day has 1440 available minutes; at 10% boost the daily
        // ceiling is 144 minutes, so only one 90-minute task fits per day.
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

    #[Test]
    public function boost_capacity_percent_unassigns_tasks_beyond_the_cap(): void
    {
        // A 50% ceiling on a single 1440-minute day is 720 minutes. A single
        // 800-minute task is beyond the cap and must be capped rather than
        // placed; a 400-minute task fits.
        $singleDay = TimeRange::from('2026-08-17T00:00:00', '2026-08-18T00:00:00');
        $input = new DraftInput(
            $singleDay,
            tasks: [
                $this->task('a', 800),
                $this->task('b', 400),
                $this->task('c', 800),
                $this->task('d', 400),
            ],
            dailyCapacityPercent: 50,
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertSame(['b'], $draft->assignedTaskIds());
        $this->assertCount(3, $draft->unassigned);
        foreach ($draft->unassigned as $unassigned) {
            $this->assertSame('CAPACITY_CAP', $unassigned->reason);
        }
    }

    #[Test]
    public function null_boost_percent_applies_no_capacity_cap(): void
    {
        // Without a boost target the normal target applies: an 800-minute task
        // that would be blocked by a 50% cap is placed normally.
        $singleDay = TimeRange::from('2026-08-17T00:00:00', '2026-08-18T00:00:00');
        $input = new DraftInput(
            $singleDay,
            tasks: [$this->task('a', 800)],
        );

        $draft = $this->generator->generate($input);

        $this->assertCount(1, $draft->assignments);
        $this->assertCount(0, $draft->unassigned);
    }
}
