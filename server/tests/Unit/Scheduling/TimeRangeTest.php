<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    #[Test]
    public function duration_is_end_minus_start(): void
    {
        $range = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T09:45:00');

        $this->assertSame(45, $range->durationMinutes()->value());
    }

    #[Test]
    public function end_must_be_after_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TimeRange::from('2026-08-19T10:00:00', '2026-08-19T10:00:00');
    }

    #[Test]
    public function half_open_intervals_do_not_overlap_at_boundary(): void
    {
        $a = TimeRange::from('2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $b = TimeRange::from('2026-08-19T11:00:00', '2026-08-19T12:00:00');

        $this->assertFalse($a->overlaps($b));
        $this->assertTrue($a->overlapsOrAdjacent($b));
    }

    #[Test]
    public function overlapping_ranges_are_detected(): void
    {
        $a = TimeRange::from('2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $b = TimeRange::from('2026-08-19T10:30:00', '2026-08-19T11:30:00');

        $this->assertTrue($a->overlaps($b));
    }

    #[Test]
    public function merge_combines_overlapping_ranges(): void
    {
        $a = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $b = TimeRange::from('2026-08-19T09:30:00', '2026-08-19T10:30:00');

        $merged = $a->merge($b);

        $this->assertSame('2026-08-19 09:00:00', $merged->start->toDateTimeString());
        $this->assertSame('2026-08-19 10:30:00', $merged->end->toDateTimeString());
    }

    #[Test]
    public function merge_rejects_disjoint_ranges(): void
    {
        $a = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $b = TimeRange::from('2026-08-19T11:00:00', '2026-08-19T12:00:00');

        $this->expectException(InvalidArgumentException::class);
        $a->merge($b);
    }

    #[Test]
    public function contains_and_instant_checks(): void
    {
        $outer = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T12:00:00');
        $inner = TimeRange::from('2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $this->assertTrue($outer->contains($inner));
        $this->assertTrue($outer->containsInstant(CarbonImmutable::parse('2026-08-19T10:00:00')));
        $this->assertFalse($outer->containsInstant(CarbonImmutable::parse('2026-08-19T12:00:00')));
    }

    #[Test]
    public function to_array_exposes_iso_boundaries(): void
    {
        $array = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T09:45:00')->toArray();

        $this->assertSame('2026-08-19T09:00:00.000000Z', $array['start']);
        $this->assertSame('2026-08-19T09:45:00.000000Z', $array['end']);
        $this->assertSame(45, $array['duration_minutes']);
    }
}
