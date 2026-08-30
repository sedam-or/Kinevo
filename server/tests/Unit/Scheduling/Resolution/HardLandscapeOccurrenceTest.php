<?php

namespace Tests\Unit\Scheduling\Resolution;

use App\Domain\Scheduling\Resolution\HardLandscapeOccurrence;
use App\Domain\Scheduling\ValueObjects\SchedulePrecedence;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HardLandscapeOccurrenceTest extends TestCase
{
    public function test_base_occurrence_keeps_effective_window_equal_to_original(): void
    {
        $start = CarbonImmutable::parse('2026-08-24 09:00');
        $end = CarbonImmutable::parse('2026-08-24 10:30');

        $occurrence = HardLandscapeOccurrence::base(
            12,
            'KRS: Algorithms',
            $start,
            $end,
            SchedulePrecedence::recurring(),
        );

        $this->assertSame(12, $occurrence->sourceEventId);
        $this->assertSame('KRS: Algorithms', $occurrence->title);
        $this->assertTrue($start->equalTo($occurrence->originalStart));
        $this->assertTrue($end->equalTo($occurrence->originalEnd));
        $this->assertTrue($start->equalTo($occurrence->effectiveStart));
        $this->assertTrue($end->equalTo($occurrence->effectiveEnd));
        $this->assertTrue($occurrence->isBase());
        $this->assertSame('base', $occurrence->provenance->value);
        $this->assertSame('recurring', $occurrence->precedence->value);
    }

    public function test_identity_is_derived_from_source_event_id_and_original_start(): void
    {
        $start = CarbonImmutable::parse('2026-08-24 02:00');

        $occurrence = HardLandscapeOccurrence::base(
            12,
            'KRS: Algorithms',
            $start,
            $start->addMinutes(90),
            SchedulePrecedence::recurring(),
        );

        $this->assertSame('12|'.$start->toISOString(), $occurrence->identity());
    }

    public function test_identity_is_stable_across_repeated_instantiation(): void
    {
        $build = fn () => HardLandscapeOccurrence::base(
            7,
            'Gym',
            CarbonImmutable::parse('2026-08-25 06:00'),
            CarbonImmutable::parse('2026-08-25 07:00'),
            SchedulePrecedence::hardLandscape(),
        );

        $this->assertSame($build()->identity(), $build()->identity());
    }

    public function test_time_range_uses_the_effective_window(): void
    {
        $occurrence = HardLandscapeOccurrence::base(
            3,
            'Dentist',
            CarbonImmutable::parse('2026-08-26 14:00'),
            CarbonImmutable::parse('2026-08-26 15:00'),
            SchedulePrecedence::hardLandscape(),
        );

        $this->assertTrue($occurrence->timeRange()->start->equalTo($occurrence->effectiveStart));
        $this->assertTrue($occurrence->timeRange()->end->equalTo($occurrence->effectiveEnd));
        $this->assertSame(60, $occurrence->timeRange()->durationMinutes()->value());
    }

    public function test_to_array_exposes_the_adr_contract_fields(): void
    {
        $occurrence = HardLandscapeOccurrence::base(
            9,
            'Lecture',
            CarbonImmutable::parse('2026-08-24 09:00'),
            CarbonImmutable::parse('2026-08-24 10:00'),
            SchedulePrecedence::recurring(),
        );

        $payload = $occurrence->toArray();

        $this->assertSame([
            'source_event_id' => 9,
            'title' => 'Lecture',
            'original_start' => '2026-08-24T09:00:00.000000Z',
            'original_end' => '2026-08-24T10:00:00.000000Z',
            'effective_start' => '2026-08-24T09:00:00.000000Z',
            'effective_end' => '2026-08-24T10:00:00.000000Z',
            'provenance' => 'base',
            'precedence' => 'recurring',
        ], $payload);
    }

    public function test_rejects_non_positive_source_event_id(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HardLandscapeOccurrence::base(0, 'X', CarbonImmutable::parse('2026-08-24 09:00'), CarbonImmutable::parse('2026-08-24 10:00'), SchedulePrecedence::recurring());
    }

    public function test_rejects_empty_title(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HardLandscapeOccurrence::base(1, '  ', CarbonImmutable::parse('2026-08-24 09:00'), CarbonImmutable::parse('2026-08-24 10:00'), SchedulePrecedence::recurring());
    }

    public function test_rejects_inverted_windows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HardLandscapeOccurrence::base(1, 'X', CarbonImmutable::parse('2026-08-24 10:00'), CarbonImmutable::parse('2026-08-24 09:00'), SchedulePrecedence::recurring());
    }
}
