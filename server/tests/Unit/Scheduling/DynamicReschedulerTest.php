<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DraftInput;
use App\Domain\Scheduling\DynamicRescheduler;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\ScheduleDraftGenerator;
use App\Domain\Scheduling\ScheduleState;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\ScheduleVersionConflict;
use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DynamicReschedulerTest extends TestCase
{
    private TimeRange $week;

    private DynamicRescheduler $rescheduler;

    protected function setUp(): void
    {
        $this->week = TimeRange::from('2026-08-17T00:00:00', '2026-08-24T00:00:00');
        $this->rescheduler = new DynamicRescheduler(
            new ScheduleDraftGenerator(
                new SlotCalculator,
                HardConstraintEngine::default(),
                TaskRankingEngine::default(),
            ),
            HardConstraintEngine::default(),
        );
    }

    private function range(string $start, string $end): TimeRange
    {
        return TimeRange::from($start, $end);
    }

    private function task(string $id, int $minutes, array $overrides = []): ScheduleTask
    {
        return new ScheduleTask(
            taskId: $id,
            title: $overrides['title'] ?? "Task {$id}",
            durationMinutes: $minutes,
            priorityTier: $overrides['priorityTier'] ?? PriorityTier::p3(),
            taskDeadline: $overrides['taskDeadline'] ?? null,
            isLocked: $overrides['isLocked'] ?? false,
            isSacredAnchor: $overrides['isSacredAnchor'] ?? false,
            existingSlot: $overrides['existingSlot'] ?? null,
        );
    }

    #[Test]
    public function proposing_produces_diff_without_mutating_schedule(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );

        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
            tasks: [$this->task('t1', 60)],
        ));

        $this->assertTrue($proposal->hasChanges());
        $this->assertCount(1, $proposal->moves);
        $this->assertSame('t1', $proposal->moves[0]->taskId);
        $this->assertSame(1, $proposal->baseVersion->value);
        $this->assertSame(2, $proposal->newVersion->value);
        $this->assertSame(1, $current->version->value);
    }

    #[Test]
    public function applying_moves_tasks_and_bumps_version(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
            tasks: [$this->task('t1', 60)],
        ));

        $applied = $this->rescheduler->apply($current, $proposal);

        $this->assertNotEquals($this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00'), $applied->slotFor('t1'));
        $this->assertSame(2, $applied->version->value);
        $this->assertTrue($applied->isConsistent());
    }

    #[Test]
    public function apply_rejects_stale_proposal_with_version_conflict(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60)],
        ));

        $newer = $current->withAssignments($current->assignments);

        $this->expectException(ScheduleVersionConflict::class);
        $this->rescheduler->apply($newer, $proposal);
    }

    #[Test]
    public function locked_tasks_are_never_moved(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['locked' => $this->range('2026-08-18T14:00:00', '2026-08-18T15:00:00')],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-18T14:00:00', '2026-08-18T15:00:00')],
            tasks: [$this->task('locked', 60, ['isLocked' => true, 'existingSlot' => $this->range('2026-08-18T14:00:00', '2026-08-18T15:00:00')])],
        ));

        $this->assertFalse($proposal->hasChanges());
    }

    #[Test]
    public function no_change_produces_empty_diff(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            tasks: [$this->task('t1', 60)],
        ));

        $this->assertFalse($proposal->hasChanges());
        $this->assertSame([], $proposal->moves);
    }

    #[Test]
    public function unplaceable_task_is_flagged_as_conflict(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-17T00:00:00', '2026-08-24T00:00:00')],
            tasks: [$this->task('t1', 60)],
        ));

        $this->assertContains('t1', $proposal->conflictTaskIds);
    }

    #[Test]
    public function cancel_means_no_schedule_mutation(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            ['t1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
        );
        $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
            tasks: [$this->task('t1', 60)],
        ));

        $this->assertSame(1, $current->version->value);
        $this->assertSame('2026-08-18 10:00:00', $current->slotFor('t1')->start->toDateTimeString());
    }

    #[Test]
    public function resulting_assignments_are_atomic_and_consistent(): void
    {
        $current = new ScheduleState(
            new ScheduleVersion(1),
            [
                't1' => $this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00'),
                't2' => $this->range('2026-08-18T14:00:00', '2026-08-18T15:00:00'),
            ],
        );
        $proposal = $this->rescheduler->propose($current, new DraftInput(
            $this->week,
            hardLandscape: [$this->range('2026-08-18T10:00:00', '2026-08-18T11:00:00')],
            tasks: [$this->task('t1', 60), $this->task('t2', 60)],
        ));

        $applied = $this->rescheduler->apply($current, $proposal);

        $this->assertSame(2, $applied->version->value);
        $this->assertTrue($applied->isConsistent());
        $this->assertCount(2, $applied->assignments);
    }
}
