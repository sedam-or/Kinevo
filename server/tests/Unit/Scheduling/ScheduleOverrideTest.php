<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use App\Domain\Scheduling\ValueObjects\SchedulePrecedence;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ScheduleOverrideTest extends TestCase
{
    public function test_create_one_time_override(): void
    {
        $override = ScheduleOverride::create(
            1,
            10,
            ScheduleOverrideType::oneTime(),
            '2026-08-19T09:00:00',
            '2026-08-19T09:00:00',
            '2026-08-19T14:00:00',
            '2026-08-19T14:30:00',
            'Standup moved',
        );

        $this->assertSame(1, $override->userId);
        $this->assertSame(10, $override->hardLandscapeEventId);
        $this->assertTrue($override->type->equals(ScheduleOverrideType::oneTime()));
        $this->assertSame('2026-08-19', $override->effectiveFrom->toDateString());
        $this->assertSame(30, $override->overrideRange()->durationMinutes()->value());
        $this->assertSame('Standup moved', $override->reason);
    }

    public function test_create_permanent_override(): void
    {
        $override = ScheduleOverride::create(
            1,
            10,
            ScheduleOverrideType::permanent(),
            '2026-08-19T00:00:00',
            '2026-08-30T00:00:00',
            '2026-08-19T15:00:00',
            '2026-08-19T15:30:00',
        );

        $this->assertTrue($override->type->equals(ScheduleOverrideType::permanent()));
        $this->assertTrue($override->effectiveTo->greaterThan($override->effectiveFrom));
    }

    public function test_create_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleOverride::create(
            1,
            10,
            ScheduleOverrideType::oneTime(),
            '2026-08-19T09:00:00',
            '2026-08-19T09:00:00',
            '2026-08-19T14:00:00',
            '2026-08-19T13:00:00',
        );
    }

    public function test_one_time_requires_single_occurrence_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleOverride::create(
            1,
            10,
            ScheduleOverrideType::oneTime(),
            '2026-08-19T00:00:00',
            '2026-08-20T00:00:00',
            '2026-08-19T14:00:00',
            '2026-08-19T14:30:00',
        );
    }

    public function test_overlap_detection_same_source(): void
    {
        $a = ScheduleOverride::create(1, 10, ScheduleOverrideType::oneTime(), '2026-08-19T09:00:00', '2026-08-19T09:00:00', '2026-08-19T14:00:00', '2026-08-19T15:00:00');
        $b = ScheduleOverride::create(1, 10, ScheduleOverrideType::oneTime(), '2026-08-20T09:00:00', '2026-08-20T09:00:00', '2026-08-20T14:30:00', '2026-08-20T15:30:00');

        $this->assertFalse($a->overlapsOverrideWith($b));
    }

    public function test_precedence_chain_order(): void
    {
        $hardLandscape = SchedulePrecedence::hardLandscape();
        $locked = SchedulePrecedence::lockedTask();
        $override = SchedulePrecedence::explicitOverride();
        $recurring = SchedulePrecedence::recurring();
        $ordinary = SchedulePrecedence::ordinary();

        $this->assertTrue($hardLandscape->dominates($locked));
        $this->assertTrue($locked->dominates($override));
        $this->assertTrue($override->dominates($recurring));
        $this->assertTrue($recurring->dominates($ordinary));
        $this->assertFalse($ordinary->dominates($hardLandscape));
    }
}
