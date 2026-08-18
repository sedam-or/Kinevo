<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\SlotCalculator;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SlotCalculatorTest extends TestCase
{
    private TimeRange $day;

    protected function setUp(): void
    {
        $this->day = TimeRange::from('2026-08-19T00:00:00', '2026-08-20T00:00:00');
    }

    private function slot(string $start, string $end): TimeRange
    {
        return TimeRange::from($start, $end);
    }

    #[Test]
    public function empty_day_returns_full_day_slot(): void
    {
        $slots = (new SlotCalculator)->calculate($this->day, []);

        $this->assertCount(1, $slots);
        $this->assertSame(1440, $slots[0]->durationMinutes()->value());
    }

    #[Test]
    public function gap_of_25_minutes_is_a_fillable_slot(): void
    {
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->slot('2026-08-19T10:25:00', '2026-08-19T11:00:00'),
        ];

        $slots = (new SlotCalculator)->calculate($this->day, $occupied);

        $this->assertNotEmpty($slots);
        $gap = array_values(array_filter(
            $slots,
            static fn (TimeRange $r) => $r->start->toDateTimeString() === '2026-08-19 10:00:00',
        ));
        $this->assertCount(1, $gap);
        $this->assertSame(25, $gap[0]->durationMinutes()->value());
    }

    #[Test]
    public function gap_of_14_minutes_is_not_a_fillable_slot(): void
    {
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->slot('2026-08-19T10:14:00', '2026-08-19T11:00:00'),
        ];

        $slots = (new SlotCalculator)->calculate($this->day, $occupied);

        foreach ($slots as $slot) {
            $this->assertGreaterThanOrEqual(15, $slot->durationMinutes()->value());
        }
        $this->assertFalse(in_array(
            '2026-08-19 10:00:00',
            array_map(static fn (TimeRange $r) => $r->start->toDateTimeString(), $slots),
            true,
        ));
    }

    #[Test]
    public function gap_of_exactly_15_minutes_is_a_fillable_slot(): void
    {
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->slot('2026-08-19T10:15:00', '2026-08-19T11:00:00'),
        ];

        $slots = (new SlotCalculator)->calculate($this->day, $occupied);

        $this->assertNotEmpty(array_filter(
            $slots,
            static fn (TimeRange $r) => $r->durationMinutes()->value() === 15,
        ));
    }

    #[Test]
    public function adjacent_occupied_blocks_leave_no_zero_length_gap(): void
    {
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->slot('2026-08-19T10:00:00', '2026-08-19T11:00:00'),
        ];

        $slots = (new SlotCalculator)->calculate($this->day, $occupied);

        foreach ($slots as $slot) {
            $this->assertFalse(
                $slot->start->toDateTimeString() === '2026-08-19 10:00:00'
                && $slot->end->toDateTimeString() === '2026-08-19 10:00:00'
            );
        }
    }

    #[Test]
    public function overlapping_occupied_events_are_never_available_time(): void
    {
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T11:00:00'),
            $this->slot('2026-08-19T10:00:00', '2026-08-19T12:00:00'),
        ];

        $slots = (new SlotCalculator)->calculate($this->day, $occupied);

        $mergedBlock = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T12:00:00');
        foreach ($slots as $slot) {
            $this->assertFalse($slot->overlaps($mergedBlock));
        }
    }

    #[Test]
    public function unsorted_occupied_input_yields_deterministic_slots(): void
    {
        $occupied = [
            $this->slot('2026-08-19T10:25:00', '2026-08-19T11:00:00'),
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
        ];

        $first = (new SlotCalculator)->calculate($this->day, $occupied);
        $second = (new SlotCalculator)->calculate($this->day, $occupied);

        $this->assertEquals($first, $second);
    }

    #[Test]
    public function custom_minimum_slot_duration_is_honored(): void
    {
        $calculator = new SlotCalculator(30);
        $occupied = [
            $this->slot('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            $this->slot('2026-08-19T10:20:00', '2026-08-19T11:00:00'),
        ];

        $slots = $calculator->calculate($this->day, $occupied);

        $this->assertFalse(in_array(
            '2026-08-19 10:00:00',
            array_map(static fn (TimeRange $r) => $r->start->toDateTimeString(), $slots),
            true,
        ));
    }
}
