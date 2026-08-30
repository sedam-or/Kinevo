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

    public function test_until_date_only_is_inclusive(): void
    {
        // UNTIL=20260824 → the 08-24 occurrence is included (RFC date-only
        // UNTIL extends through that local date).
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY;UNTIL=20260824',
            CarbonImmutable::parse('2026-08-17 09:00'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-09-30');

        $occurrences = $this->generator->generate($rule, $from, $to);

        $dates = array_map(static fn ($o) => $o->toDateString(), $occurrences);
        $this->assertSame(['2026-08-17', '2026-08-24'], $dates);
    }

    public function test_until_datetime_is_inclusive_at_the_exact_instant(): void
    {
        $start = CarbonImmutable::parse('2026-08-17 09:00');

        $inclusive = RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20260824T090000', $start);
        $exclusive = RecurrenceRule::parse('FREQ=WEEKLY;UNTIL=20260824T085959', $start);
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-09-30');

        $this->assertCount(2, $this->generator->generate($inclusive, $from, $to));
        $this->assertCount(1, $this->generator->generate($exclusive, $from, $to));
    }

    public function test_utc_z_until_is_normalized_to_the_start_timezone(): void
    {
        // Occurrence 2026-08-24 09:00 Asia/Jakarta == 02:00 UTC.
        $rule = RecurrenceRule::parse(
            'FREQ=WEEKLY;UNTIL=20260824T020000Z',
            CarbonImmutable::parse('2026-08-17 09:00', 'Asia/Jakarta'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-09-30');

        $this->assertCount(2, $this->generator->generate($rule, $from, $to));

        $strict = RecurrenceRule::parse(
            'FREQ=WEEKLY;UNTIL=20260824T015959Z',
            CarbonImmutable::parse('2026-08-17 09:00', 'Asia/Jakarta'),
        );

        $this->assertCount(1, $this->generator->generate($strict, $from, $to));
    }

    public function test_count_and_until_whichever_terminates_first(): void
    {
        $start = CarbonImmutable::parse('2026-08-17 09:00');
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-31');

        $untilFirst = RecurrenceRule::parse('FREQ=DAILY;COUNT=5;UNTIL=20260818', $start);
        $countFirst = RecurrenceRule::parse('FREQ=DAILY;COUNT=1;UNTIL=20260820', $start);

        $this->assertSame(
            ['2026-08-17', '2026-08-18'],
            array_map(static fn ($o) => $o->toDateString(), $this->generator->generate($untilFirst, $from, $to)),
        );
        $this->assertSame(
            ['2026-08-17'],
            array_map(static fn ($o) => $o->toDateString(), $this->generator->generate($countFirst, $from, $to)),
        );
    }

    public function test_until_beyond_window_leaves_window_bound_in_charge(): void
    {
        $rule = RecurrenceRule::parse(
            'FREQ=DAILY;UNTIL=20260930',
            CarbonImmutable::parse('2026-08-17 09:00'),
        );
        $from = CarbonImmutable::parse('2026-08-17');
        $to = CarbonImmutable::parse('2026-08-19');

        $this->assertCount(3, $this->generator->generate($rule, $from, $to));
    }
}
