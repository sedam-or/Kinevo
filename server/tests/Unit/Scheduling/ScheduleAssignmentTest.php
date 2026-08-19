<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ScheduleAssignmentTest extends TestCase
{
    public function test_can_create_scheduled_assignment(): void
    {
        $assignment = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 3,
        );

        $this->assertSame(0, $assignment->id);
        $this->assertSame(1, $assignment->userId);
        $this->assertSame(2, $assignment->taskId);
        $this->assertSame('2026-08-19', $assignment->date->toDateString());
        $this->assertSame(45, $assignment->durationMinutes);
        $this->assertTrue($assignment->status->equals(ScheduleAssignmentStatus::scheduled()));
        $this->assertTrue($assignment->source->equals(ScheduleAssignmentSource::draft()));
        $this->assertSame(3, $assignment->scheduleVersion);
        $this->assertFalse($assignment->locked);
        $this->assertSame(1, $assignment->version);
    }

    public function test_explicit_duration_must_match_start_end_diff(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match');

        ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
            durationMinutes: 30,
        );
    }

    public function test_start_must_be_before_end(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T10:00:00',
            endAt: '2026-08-19T09:00:00',
        );
    }

    public function test_zero_duration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:00:00',
        );
    }

    public function test_invalid_user_or_task_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleAssignment::create(
            userId: 0,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );
    }

    public function test_date_must_match_start_at_calendar_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('calendar date');

        ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-20',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );
    }

    public function test_invalid_status_value_object_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScheduleAssignmentStatus('unknown');
    }

    public function test_invalid_source_value_object_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ScheduleAssignmentSource('unknown');
    }

    public function test_lock_toggle_bumps_version(): void
    {
        $assignment = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );

        $locked = $assignment->withLocked(true);

        $this->assertTrue($locked->locked);
        $this->assertSame(2, $locked->version);
        $this->assertSame(1, $assignment->version);

        $same = $locked->withLocked(true);
        $this->assertSame($locked, $same);
    }

    public function test_complete_and_cancel_change_status_and_version(): void
    {
        $assignment = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );

        $completed = $assignment->complete();
        $this->assertTrue($completed->status->equals(ScheduleAssignmentStatus::completed()));
        $this->assertSame(2, $completed->version);

        $cancelled = $completed->cancel();
        $this->assertTrue($cancelled->status->equals(ScheduleAssignmentStatus::cancelled()));
        $this->assertSame(3, $cancelled->version);
    }

    public function test_time_range_update_recomputes_duration_and_bumps_version(): void
    {
        $assignment = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );

        $moved = $assignment->withTimeRange(
            date: '2026-08-19',
            startAt: '2026-08-19T10:00:00',
            endAt: '2026-08-19T10:30:00',
        );

        $this->assertSame('2026-08-19 10:00:00', $moved->startAt->toDateTimeString());
        $this->assertSame(30, $moved->durationMinutes);
        $this->assertSame(2, $moved->version);
    }

    public function test_overlap_detection_uses_half_open_semantics(): void
    {
        $a = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        );

        $overlapping = ScheduleAssignment::create(
            userId: 1,
            taskId: 3,
            date: '2026-08-19',
            startAt: '2026-08-19T09:30:00',
            endAt: '2026-08-19T10:00:00',
        );

        $adjacent = ScheduleAssignment::create(
            userId: 1,
            taskId: 4,
            date: '2026-08-19',
            startAt: '2026-08-19T09:45:00',
            endAt: '2026-08-19T10:00:00',
        );

        $this->assertTrue($a->overlapsWith($overlapping));
        $this->assertFalse($a->overlapsWith($adjacent));
    }

    public function test_to_array_exposes_required_fields(): void
    {
        $assignment = ScheduleAssignment::create(
            userId: 1,
            taskId: 2,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 5,
            locked: true,
        );

        $array = $assignment->toArray();

        $this->assertSame(0, $array['id']);
        $this->assertSame(1, $array['user_id']);
        $this->assertSame(2, $array['task_id']);
        $this->assertSame('2026-08-19', $array['date']);
        $this->assertSame(45, $array['duration_minutes']);
        $this->assertSame('scheduled', $array['status']);
        $this->assertSame('draft', $array['source']);
        $this->assertSame(5, $array['schedule_version']);
        $this->assertTrue($array['locked']);
        $this->assertSame(1, $array['version']);
    }
}
