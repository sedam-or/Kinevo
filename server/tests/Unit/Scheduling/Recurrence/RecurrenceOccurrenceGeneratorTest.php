<?php

namespace Tests\Unit\Scheduling\Recurrence;

use App\Domain\Scheduling\Recurrence\RecurrenceOccurrenceGenerator;
use App\Domain\Scheduling\Recurrence\RecurrenceRule;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RecurrenceOccurrenceGeneratorTest extends TestCase
{
    private RecurrenceOccurrenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RecurrenceOccurrenceGenerator;
    }

    public function test_daily_recurrence_generates_one_occurrence_per_day(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY', CarbonImmutable::parse('2026-08-17 09:00'));
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-19');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $this->assertCount(3, $occurrences);
        $this->assertSame('2026-08-17', $occurrences[0]->toDateString());
        $this->assertSame('2026-08-19', $occurrences[2]->toDateString());
        $this->assertSame(9, $occurrences[0]->hour);
    }

    public function test_weekly_recurrence_generates_same_weekday(): void
    {
        // 2026-08-17 is a Monday. Weekly → every Monday.
        $rule = RecurrenceRule::parse('FREQ=WEEKLY', CarbonImmutable::parse('2026-08-17 09:00'));
        $from = CarbonImmutable::parse('2026-08-10');
        $to = CarbonImmutable::parse('2026-08-31');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-24', '2026-08-31'], $dates);
    }

    public function test_multiple_weekdays_recurrence(): void
    {
        // Weekly on Mon/Wed/Fri.
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            CarbonImmutable::parse('2026-08-17 09:00'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-21');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-19', '2026-08-21'], $dates);
    }

    public function test_timezone_boundary_keeps_local_time_of_day(): void
    {
        // A weekly event at 23:30 Asia/Jakarta (UTC+7). The occurrence must stay
        // on the same local date/time, not drift across a UTC day boundary.
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY',
            CarbonImmutable::parse('2026-08-17 23:30', 'Asia/Jakarta'),
        );
        $from = CarbonImmutable::parse('2026-08-17 00:00', 'Asia/Jakarta');
        $to = CarbonImmutable::parse('2026-08-31 00:00', 'Asia/Jakarta');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $this->assertCount(3, $occurrences);
        $first = $occurrences[0];
        $this->assertSame('2026-08-17', $first->toDateString());
        $this->assertSame(23, $first->hour);
        $this->assertSame(30, $first->minute);
        $this->assertSame('Asia/Jakarta', $first->timezone->getName());

        // Each occurrence is exactly one week apart in local time.
        $this->assertSame('2026-08-24', $occurrences[1]->toDateString());
        $this->assertSame('2026-08-31', $occurrences[2]->toDateString());
    }

    public function test_exception_day_is_skipped(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY', CarbonImmutable::parse('2026-08-17 09:00'));
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-20');
        $excluded = [CarbonImmutable::parse('2026-08-18')];

        $occurrences = $this->generator->generate($rule, $from, $to, $excluded);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-19', '2026-08-20'], $dates);
    }

    public function test_deleted_occurrence_is_skipped(): void
    {
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY;BYDAY=MO,WE,FR',
            CarbonImmutable::parse('2026-08-17 09:00'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-21');
        $deleted = [CarbonImmutable::parse('2026-08-19')];

        $occurrences = $this->generator->generate($rule, $from, $to, $deleted);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-21'], $dates);
    }

    public function test_duplicate_prevention(): void
    {
        // A weekly rule with the full weekday set could, combined with the
        // window, yield unique days; force dedupe by passing a duplicate-free
        // expectation: overlapping COUNT + window must not repeat dates.
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR;COUNT=10',
            CarbonImmutable::parse('2026-08-17 09:00'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-28');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame($dates, array_values(array_unique($dates)));
        // 10 weekdays across the window (17..21 and 24..28).
        $this->assertCount(10, $dates);
    }

    public function test_count_limits_occurrences(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY;COUNT=3', CarbonImmutable::parse('2026-08-17 09:00'));
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-24');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $this->assertCount(3, $occurrences);
        $this->assertSame('2026-08-17', $occurrences[0]->toDateString());
        $this->assertSame('2026-08-19', $occurrences[2]->toDateString());
    }

    public function test_interval_skips_days(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY;INTERVAL=2', CarbonImmutable::parse('2026-08-17 09:00'));
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-23');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-19', '2026-08-21', '2026-08-23'], $dates);
    }

    public function test_parse_rejects_unsupported_frequency(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurrenceRule::parse('FREQ=MONTHLY', CarbonImmutable::parse('2026-08-17'));
    }

    public function test_parse_rejects_invalid_byday(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecurrenceRule::parse('FREQ=WEEKLY;BYDAY=XX', CarbonImmutable::parse('2026-08-17'));
    }

    public function test_max_occurrences_guard(): void
    {
        $rule = RecurrenceRule::parse('FREQ=DAILY', CarbonImmutable::parse('2026-01-01 09:00'));
        $from = CarbonImmutable::parse('2026-01-01');
        $to = CarbonImmutable::parse('2030-01-01');

        $occurrences = $this->generator->generate($rule, $from, $to, maxOccurrences: 5);

        $this->assertCount(5, $occurrences);
    }
}
