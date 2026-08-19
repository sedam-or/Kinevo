<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HardLandscapeEventTest extends TestCase
{
    public function test_create_builds_valid_event(): void
    {
        $event = HardLandscapeEvent::create(
            1,
            'Standup',
            HardLandscapeType::oneTime(),
            '2026-08-19T09:00:00',
            '2026-08-19T09:30:00',
        );

        $this->assertSame(1, $event->userId);
        $this->assertSame('Standup', $event->title);
        $this->assertTrue($event->type->equals(HardLandscapeType::oneTime()));
        $this->assertSame(30, $event->timeRange()->durationMinutes()->value());
    }

    public function test_create_rejects_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HardLandscapeEvent::create(1, '   ', HardLandscapeType::oneTime(), '2026-08-19T09:00:00', '2026-08-19T09:30:00');
    }

    public function test_create_rejects_end_before_start(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HardLandscapeEvent::create(1, 'X', HardLandscapeType::oneTime(), '2026-08-19T10:00:00', '2026-08-19T09:00:00');
    }

    public function test_recurring_requires_recurrence_rule(): void
    {
        $this->expectException(InvalidArgumentException::class);

        HardLandscapeEvent::create(1, 'Daily', HardLandscapeType::recurring(), '2026-08-19T09:00:00', '2026-08-19T09:30:00');
    }

    public function test_recurring_accepts_recurrence_rule(): void
    {
        $event = HardLandscapeEvent::create(
            1,
            'Daily',
            HardLandscapeType::recurring(),
            '2026-08-19T09:00:00',
            '2026-08-19T09:30:00',
            'FREQ=DAILY',
        );

        $this->assertSame('FREQ=DAILY', $event->recurrence);
    }

    public function test_overlap_detection(): void
    {
        $a = HardLandscapeEvent::create(1, 'A', HardLandscapeType::oneTime(), '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $b = HardLandscapeEvent::create(1, 'B', HardLandscapeType::oneTime(), '2026-08-19T09:30:00', '2026-08-19T10:30:00');

        $this->assertTrue($a->overlapsWith($b));
    }

    public function test_occurs_on_matching_date(): void
    {
        $event = HardLandscapeEvent::create(1, 'A', HardLandscapeType::oneTime(), '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->assertTrue($event->occursOn(CarbonImmutable::parse('2026-08-19')));
        $this->assertFalse($event->occursOn(CarbonImmutable::parse('2026-08-20')));
    }
}
