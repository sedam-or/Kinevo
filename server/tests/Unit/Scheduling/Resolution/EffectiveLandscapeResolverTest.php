<?php

namespace Tests\Unit\Scheduling\Resolution;

use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\Resolution\EffectiveLandscapeResolver;
use App\Domain\Scheduling\Resolution\OccurrenceProvenance;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * ES-IMPL-01 acceptance matrix (ADR-015 Phase 17): pure resolver semantics —
 * base + recurrence expansion only. No override resolution (ES-IMPL-04/05),
 * no read-model integration (ES-IMPL-02), no scheduler integration
 * (ES-IMPL-03).
 */
final class EffectiveLandscapeResolverTest extends TestCase
{
    private EffectiveLandscapeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new EffectiveLandscapeResolver;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function dayWindow(string $from, string $to): array
    {
        return [
            CarbonImmutable::parse($from)->startOfDay(),
            CarbonImmutable::parse($to)->endOfDay(),
        ];
    }

    private static function event(
        int $id,
        string $type,
        string $start,
        string $end,
        ?string $recurrence = null,
        ?string $timezone = null,
        string $title = 'Block',
    ): HardLandscapeEvent {
        $startAt = CarbonImmutable::parse($start, $timezone);
        $endAt = CarbonImmutable::parse($end, $timezone);

        return HardLandscapeEvent::create(1, $title, new HardLandscapeType($type), $startAt, $endAt, $recurrence)->withId($id);
    }

    // ------------------------------------------------------------------
    // A. Non-recurring sources
    // ------------------------------------------------------------------

    public function test_permanent_source_inside_window_emits_once(): void
    {
        [$from, $to] = self::dayWindow('2026-09-01', '2026-09-01');
        $event = self::event(4, HardLandscapeType::PERMANENT, '2026-09-01 10:00', '2026-09-01 11:00');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(0, $resolution->recurrenceWarnings);
        $this->assertCount(1, $resolution->occurrences);
        $occurrence = $resolution->occurrences[0];
        $this->assertSame(4, $occurrence->sourceEventId);
        $this->assertTrue($occurrence->isBase());
        $this->assertSame('hard_landscape', $occurrence->precedence->value);
        $this->assertSame('2026-09-01T10:00:00.000000Z', $occurrence->effectiveStart->toISOString());
    }

    public function test_source_outside_window_emits_nothing(): void
    {
        [$from, $to] = self::dayWindow('2026-09-01', '2026-09-02');
        $event = self::event(4, HardLandscapeType::PERMANENT, '2026-09-10 10:00', '2026-09-10 11:00');

        $this->assertCount(0, $this->resolver->resolve([$event], [], $from, $to)->occurrences);
    }

    public function test_boundary_overlap_follows_half_open_interval_semantics(): void
    {
        // Window [2026-09-02 00:00, 2026-09-03 00:00).
        $from = CarbonImmutable::parse('2026-09-02 00:00');
        $to = CarbonImmutable::parse('2026-09-03 00:00');

        $overlappingStart = self::event(1, HardLandscapeType::PERMANENT, '2026-09-01 10:00', '2026-09-02 10:00');
        $endingExactlyAtWindowStart = self::event(2, HardLandscapeType::PERMANENT, '2026-09-01 08:00', '2026-09-02 00:00');
        $startingExactlyAtWindowEnd = self::event(3, HardLandscapeType::PERMANENT, '2026-09-03 00:00', '2026-09-03 01:00');

        $resolution = $this->resolver->resolve([$overlappingStart, $endingExactlyAtWindowStart, $startingExactlyAtWindowEnd], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $this->assertSame(1, $resolution->occurrences[0]->sourceEventId);
    }

    public function test_one_time_source_is_treated_as_a_base_row(): void
    {
        [$from, $to] = self::dayWindow('2026-09-01', '2026-09-01');
        $event = self::event(8, HardLandscapeType::ONE_TIME, '2026-09-01 18:00', '2026-09-01 19:00');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $this->assertSame('hard_landscape', $resolution->occurrences[0]->precedence->value);
    }

    public function test_inverted_window_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve([], [], CarbonImmutable::parse('2026-09-02'), CarbonImmutable::parse('2026-09-01'));
    }

    // ------------------------------------------------------------------
    // B. Weekly recurrence
    // ------------------------------------------------------------------

    public function test_weekly_occurrence_appears_next_week(): void
    {
        // 2026-08-17 is a Monday.
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:30', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-24', '2026-08-24');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $occurrence = $resolution->occurrences[0];
        $this->assertSame('2026-08-24', $occurrence->effectiveStart->toDateString());
        $this->assertSame('09:00', $occurrence->effectiveStart->format('H:i'));
        $this->assertSame('10:30', $occurrence->effectiveEnd->format('H:i'));
        $this->assertSame('recurring', $occurrence->precedence->value);
        $this->assertSame('5|'.$occurrence->originalStart->toISOString(), $occurrence->identity());
    }

    public function test_non_occurrence_day_remains_clear(): void
    {
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:30', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-25', '2026-08-26');

        $this->assertCount(0, $this->resolver->resolve([$event], [], $from, $to)->occurrences);
    }

    public function test_multiple_weeks_resolve_deterministically(): void
    {
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:30', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-30');

        $first = $this->resolver->resolve([$event], [], $from, $to);
        $second = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(2, $first->occurrences);
        $this->assertSame(
            array_map(static fn ($o) => $o->toArray(), $first->occurrences),
            array_map(static fn ($o) => $o->toArray(), $second->occurrences),
        );
        $this->assertSame('2026-08-17', $first->occurrences[0]->effectiveStart->toDateString());
        $this->assertSame('2026-08-24', $first->occurrences[1]->effectiveStart->toDateString());
    }

    public function test_recurrence_does_not_extend_beyond_requested_window(): void
    {
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:30', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-18', '2026-08-23');

        $this->assertCount(0, $this->resolver->resolve([$event], [], $from, $to)->occurrences);
    }

    // ------------------------------------------------------------------
    // C. Daily recurrence
    // ------------------------------------------------------------------

    public function test_daily_recurrence_uses_generator_semantics(): void
    {
        $event = self::event(6, HardLandscapeType::RECURRING, '2026-08-17 07:00', '2026-08-17 08:00', 'FREQ=DAILY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-19');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(3, $resolution->occurrences);
        $this->assertSame('2026-08-17', $resolution->occurrences[0]->effectiveStart->toDateString());
        $this->assertSame('2026-08-19', $resolution->occurrences[2]->effectiveStart->toDateString());
    }

    public function test_daily_interval_is_respected(): void
    {
        $event = self::event(6, HardLandscapeType::RECURRING, '2026-08-17 07:00', '2026-08-17 08:00', 'FREQ=DAILY;INTERVAL=2');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-22');

        $dates = array_map(
            static fn ($o) => $o->effectiveStart->toDateString(),
            $this->resolver->resolve([$event], [], $from, $to)->occurrences,
        );

        $this->assertSame(['2026-08-17', '2026-08-19', '2026-08-21'], $dates);
    }

    // ------------------------------------------------------------------
    // D. BYDAY
    // ------------------------------------------------------------------

    public function test_byday_expansion_is_preserved_and_unique_per_date(): void
    {
        $event = self::event(7, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY;BYDAY=MO,WE,FR');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-23');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $dates = array_map(
            static fn ($o) => $o->effectiveStart->toDateString(),
            $resolution->occurrences,
        );

        // Current-v1 invariant: one canonical occurrence per source per local
        // calendar date (one_time override date-targeting compatibility).
        $this->assertSame(['2026-08-17', '2026-08-19', '2026-08-21'], $dates);
        $this->assertSame($dates, array_unique($dates));
    }

    // ------------------------------------------------------------------
    // E. COUNT / UNTIL
    // ------------------------------------------------------------------

    public function test_count_stops_generation(): void
    {
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY;COUNT=2');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-09-30');

        $this->assertCount(2, $this->resolver->resolve([$event], [], $from, $to)->occurrences);
    }

    public function test_until_stops_generation(): void
    {
        // ES-FIX-00: UNTIL is enforced by the canonical generator (inclusive
        // date-only boundary) — a resolver consumer never filters UNTIL.
        $event = self::event(5, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY;UNTIL=20260824');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-09-30');

        $dates = array_map(
            static fn ($o) => $o->effectiveStart->toDateString(),
            $this->resolver->resolve([$event], [], $from, $to)->occurrences,
        );

        $this->assertSame(['2026-08-17', '2026-08-24'], $dates);
    }

    // ------------------------------------------------------------------
    // F. Timezone
    // ------------------------------------------------------------------

    public function test_wall_clock_time_stays_stable_in_the_source_timezone(): void
    {
        // Stored 02:00 UTC == 09:00 Asia/Jakarta weekly lecture.
        $event = self::event(11, HardLandscapeType::RECURRING, '2026-08-17 02:00', '2026-08-17 03:30', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-24', '2026-08-24');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $occurrence = $resolution->occurrences[0];
        $this->assertSame('02:00', $occurrence->effectiveStart->format('H:i'));
        $this->assertSame(
            '09:00',
            $occurrence->effectiveStart->timezone('Asia/Jakarta')->format('H:i'),
        );
    }

    public function test_source_timezone_wall_clock_is_pinned_by_the_generator(): void
    {
        // A source carrying a non-UTC timezone keeps its local wall-clock on
        // every occurrence (RecurrenceOccurrenceGenerator contract).
        $event = self::event(
            12,
            HardLandscapeType::RECURRING,
            '2026-08-17 09:00',
            '2026-08-17 10:00',
            'FREQ=WEEKLY',
            'Asia/Jakarta',
        );
        [$from, $to] = self::dayWindow('2026-08-24', '2026-08-24');

        $occurrence = $this->resolver->resolve([$event], [], $from, $to)->occurrences[0];

        $this->assertSame('2026-08-24', $occurrence->effectiveStart->toDateString());
        $this->assertSame('09:00', $occurrence->effectiveStart->timezone('Asia/Jakarta')->format('H:i'));
        $this->assertSame('02:00', $occurrence->effectiveStart->timezone('UTC')->format('H:i'));
    }

    public function test_dst_observing_timezone_keeps_wall_clock_across_the_transition(): void
    {
        // Europe/Berlin DST ends 2026-10-25 (last Sunday of October). A
        // weekly Sunday 09:00 lecture must stay 09:00 local on both sides of
        // the transition; its UTC instant shifts by one hour.
        $event = self::event(
            13,
            HardLandscapeType::RECURRING,
            '2026-10-18 09:00',
            '2026-10-18 10:00',
            'FREQ=WEEKLY',
            'Europe/Berlin',
        );
        [$from, $to] = self::dayWindow('2026-10-18', '2026-10-25');

        $occurrences = $this->resolver->resolve([$event], [], $from, $to)->occurrences;

        $this->assertCount(2, $occurrences);
        $beforeDst = $occurrences[0]->effectiveStart;
        $afterDst = $occurrences[1]->effectiveStart;

        $this->assertSame('2026-10-18', $beforeDst->toDateString());
        $this->assertSame('2026-10-25', $afterDst->toDateString());
        $this->assertSame('09:00', $beforeDst->timezone('Europe/Berlin')->format('H:i'));
        $this->assertSame('09:00', $afterDst->timezone('Europe/Berlin')->format('H:i'));
        $this->assertNotSame(
            $beforeDst->timezone('UTC')->format('H:i'),
            $afterDst->timezone('UTC')->format('H:i'),
            'UTC instant must shift across DST while local wall-clock stays pinned.',
        );
    }

    // ------------------------------------------------------------------
    // G. Identity
    // ------------------------------------------------------------------

    public function test_identity_is_deterministic_for_source_and_window(): void
    {
        $event = self::event(21, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-24', '2026-08-24');

        $first = $this->resolver->resolve([$event], [], $from, $to)->occurrences[0]->identity();
        $second = (new EffectiveLandscapeResolver)->resolve([$event], [], $from, $to)->occurrences[0]->identity();

        $this->assertSame($first, $second);
        $this->assertStringStartsWith('21|2026-08-24', $first);
    }

    public function test_identity_stable_across_windows_containing_the_same_occurrence(): void
    {
        $event = self::event(21, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$weekFrom, $weekTo] = self::dayWindow('2026-08-24', '2026-08-24');
        [$monthFrom, $monthTo] = self::dayWindow('2026-08-01', '2026-08-31');

        $fromWeek = $this->resolver->resolve([$event], [], $weekFrom, $weekTo)->occurrences[0];
        $fromMonth = $this->resolver->resolve([$event], [], $monthFrom, $monthTo)->occurrences[1];

        $this->assertSame($fromWeek->identity(), $fromMonth->identity());
    }

    public function test_no_occurrence_is_persisted_or_randomized(): void
    {
        $event = self::event(22, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-31');

        $first = array_map(static fn ($o) => $o->toArray(), $this->resolver->resolve([$event], [], $from, $to)->occurrences);
        $second = array_map(static fn ($o) => $o->toArray(), $this->resolver->resolve([$event], [], $from, $to)->occurrences);

        $this->assertSame($first, $second);
        foreach ($first as $occurrence) {
            $this->assertArrayNotHasKey('id', $occurrence);
        }
    }

    // ------------------------------------------------------------------
    // H. Determinism
    // ------------------------------------------------------------------

    public function test_input_order_does_not_alter_output_order(): void
    {
        $permanent = self::event(2, HardLandscapeType::PERMANENT, '2026-08-18 08:00', '2026-08-18 09:00');
        $recurring = self::event(9, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-19');

        $ordered = $this->resolver->resolve([$permanent, $recurring], [], $from, $to);
        $shuffled = $this->resolver->resolve([$recurring, $permanent], [], $from, $to);

        $this->assertSame(
            array_map(static fn ($o) => $o->toArray(), $ordered->occurrences),
            array_map(static fn ($o) => $o->toArray(), $shuffled->occurrences),
        );
    }

    public function test_equal_effective_start_ties_break_by_source_event_id(): void
    {
        $later = self::event(5, HardLandscapeType::PERMANENT, '2026-08-18 08:00', '2026-08-18 09:00');
        $earlier = self::event(2, HardLandscapeType::PERMANENT, '2026-08-18 08:00', '2026-08-18 08:30');
        [$from, $to] = self::dayWindow('2026-08-18', '2026-08-18');

        $occurrences = $this->resolver->resolve([$later, $earlier], [], $from, $to)->occurrences;

        $this->assertSame([2, 5], array_map(static fn ($o) => $o->sourceEventId, $occurrences));
    }

    // ------------------------------------------------------------------
    // I. Failure contract
    // ------------------------------------------------------------------

    public function test_malformed_recurrence_degrades_to_base_occurrence_with_warning(): void
    {
        // Export contract: an unparseable rule degrades to the base event so
        // the block is never silently dropped (ExportScheduleIcsUseCase).
        $event = self::event(31, HardLandscapeType::RECURRING, '2026-08-18 10:00', '2026-08-18 11:00', 'NOT_A_RULE');
        [$from, $to] = self::dayWindow('2026-08-18', '2026-08-18');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $this->assertSame('2026-08-18T10:00:00.000000Z', $resolution->occurrences[0]->effectiveStart->toISOString());
        $this->assertCount(1, $resolution->recurrenceWarnings);
        $warning = $resolution->recurrenceWarnings[0]->toArray();
        $this->assertSame(31, $warning['source_event_id']);
        $this->assertSame('NOT_A_RULE', $warning['recurrence']);
        $this->assertStringContainsString('FREQ', $warning['reason']);
    }

    public function test_invalid_until_value_degrades_with_warning(): void
    {
        $event = self::event(32, HardLandscapeType::RECURRING, '2026-08-18 10:00', '2026-08-18 11:00', 'FREQ=WEEKLY;UNTIL=BAD');
        [$from, $to] = self::dayWindow('2026-08-18', '2026-08-18');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $this->assertCount(1, $resolution->recurrenceWarnings);
        $this->assertStringContainsString('UNTIL', $resolution->recurrenceWarnings[0]->reason);
    }

    public function test_degraded_base_occurrence_outside_window_is_window_filtered(): void
    {
        $event = self::event(33, HardLandscapeType::RECURRING, '2026-09-10 10:00', '2026-09-10 11:00', 'BROKEN');
        [$from, $to] = self::dayWindow('2026-08-18', '2026-08-18');

        $resolution = $this->resolver->resolve([$event], [], $from, $to);

        $this->assertCount(0, $resolution->occurrences);
        $this->assertCount(1, $resolution->recurrenceWarnings);
    }

    // ------------------------------------------------------------------
    // J. Bounds
    // ------------------------------------------------------------------

    public function test_month_like_window_yields_exactly_the_window_days(): void
    {
        $event = self::event(41, HardLandscapeType::RECURRING, '2026-08-01 09:00', '2026-08-01 09:30', 'FREQ=DAILY');
        [$from, $to] = self::dayWindow('2026-08-01', '2026-08-31');

        $this->assertCount(31, $this->resolver->resolve([$event], [], $from, $to)->occurrences);
    }

    public function test_window_bounds_expansion_even_for_a_huge_count(): void
    {
        // The resolver never bypasses the generator's bounds: a misconfigured
        // 5000-count daily series still cannot escape the requested window
        // (and the generator's own max-occurrence guard remains in force).
        $event = self::event(42, HardLandscapeType::RECURRING, '2026-01-01 09:00', '2026-01-01 09:30', 'FREQ=DAILY;COUNT=5000');
        [$from, $to] = self::dayWindow('2026-01-01', '2027-12-31');

        $this->assertCount(
            730,
            $this->resolver->resolve([$event], [], $from, $to)->occurrences,
            'Two non-leap years of daily occurrences — bounded by the window, never unbounded.',
        );
    }

    // ------------------------------------------------------------------
    // Forward compatibility: overrides must not alter base output
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    // ES-IMPL-04/05 — override resolution (ADR-015 precedence)
    // ------------------------------------------------------------------

    private function override(
        int $id,
        int $sourceId,
        string $type,
        string $effectiveFrom,
        string $effectiveTo,
        string $overrideStart,
        string $overrideEnd,
        bool $cancels = false,
    ): ScheduleOverride {
        return ScheduleOverride::create(
            1,
            $sourceId,
            new ScheduleOverrideType($type),
            $effectiveFrom,
            $effectiveTo,
            $overrideStart,
            $overrideEnd,
            null,
            $cancels,
        )->withId($id);
    }

    public function test_one_time_exception_moves_exactly_the_target_occurrence(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-31');
        $exception = $this->override(3, 51, 'one_time', '2026-08-24 00:00', '2026-08-24 00:00', '2026-08-24 15:00', '2026-08-24 16:00');

        $resolution = $this->resolver->resolve([$event], [$exception], $from, $to);

        $this->assertCount(3, $resolution->occurrences);
        $this->assertSame('base', $resolution->occurrences[0]->provenance->value);          // 08-17 unchanged
        $target = $resolution->occurrences[1];
        $this->assertSame('excepted:3', $target->provenance->value);                        // 08-24 moved
        $this->assertSame('2026-08-24T15:00:00.000000Z', $target->effectiveStart->toISOString());
        $this->assertSame('explicit_override', $target->precedence->value);
        $this->assertSame('base', $resolution->occurrences[2]->provenance->value);          // 08-31 unchanged
    }

    public function test_cancelling_exception_removes_only_the_target_occurrence(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-31');
        $cancellation = $this->override(4, 51, 'one_time', '2026-08-24 00:00', '2026-08-24 00:00', '2026-08-24 09:00', '2026-08-24 10:00', true);

        $resolution = $this->resolver->resolve([$event], [$cancellation], $from, $to);

        $dates = array_map(static fn ($o) => $o->effectiveStart->toDateString(), $resolution->occurrences);
        $this->assertSame(['2026-08-17', '2026-08-31'], $dates, 'Only the target occurrence is removed.');
        $this->assertCount(1, $resolution->cancelledOccurrences);
        $this->assertSame('cancelled:4', $resolution->cancelledOccurrences[0]->provenance->value);
    }

    public function test_exception_beats_permanent_shift_for_the_target_date(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-08-17', '2026-08-31');
        $shift = $this->override(2, 51, 'permanent', '2026-08-17 00:00', '2026-12-31 00:00', '2026-08-19 13:00', '2026-08-19 14:00');
        $exception = $this->override(3, 51, 'one_time', '2026-08-24 00:00', '2026-08-24 00:00', '2026-08-24 18:00', '2026-08-24 19:00');

        $resolution = $this->resolver->resolve([$event], [$shift, $exception], $from, $to);

        $byDate = [];
        foreach ($resolution->occurrences as $o) {
            $byDate[$o->effectiveStart->toDateString()] = $o->provenance->value;
        }
        $this->assertSame('shifted:2', $byDate['2026-08-19']);
        $this->assertSame('excepted:3', $byDate['2026-08-24']);
    }

    public function test_permanent_shift_retimes_every_covered_occurrence_and_preserves_the_source(): void
    {
        // BASE Monday 09:00–10:00 → SHIFT effective 2026-09-14: Wednesday 13:00–14:00.
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-09-07 09:00', '2026-09-07 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-09-07', '2026-09-28');
        $shift = $this->override(2, 51, 'permanent', '2026-09-14 00:00', '2026-12-31 00:00', '2026-09-16 13:00', '2026-09-16 14:00');

        $resolution = $this->resolver->resolve([$event], [$shift], $from, $to);

        $before = $resolution->occurrences[0];
        $this->assertSame('2026-09-07', $before->effectiveStart->toDateString());
        $this->assertSame('09:00', $before->effectiveStart->format('H:i'));
        $this->assertSame('base', $before->provenance->value);

        $coveredFirst = $resolution->occurrences[1];
        $this->assertSame('2026-09-16', $coveredFirst->effectiveStart->toDateString(), 'Covered occurrence 09-14 lands on Wednesday.');
        $this->assertSame('13:00', $coveredFirst->effectiveStart->format('H:i'));
        $this->assertSame('shifted:2', $coveredFirst->provenance->value);

        $coveredNext = $resolution->occurrences[2];
        $this->assertSame('2026-09-23', $coveredNext->effectiveStart->toDateString(), 'The following covered week is Wednesday too.');
        $this->assertSame('13:00', $coveredNext->effectiveStart->format('H:i'));

        // The source event is never mutated.
        $this->assertSame('2026-09-07 09:00', $event->startAt->format('Y-m-d H:i'));
        $this->assertSame('FREQ=WEEKLY', $event->recurrence);
    }

    public function test_latest_applicable_shift_wins_deterministically(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-09-07 09:00', '2026-09-07 10:00', 'FREQ=WEEKLY');
        // Query the EFFECTIVE date: the 09-14 occurrence is re-timed onto 09-16.
        [$from, $to] = self::dayWindow('2026-09-16', '2026-09-16');
        $older = $this->override(1, 51, 'permanent', '2026-09-14 00:00', '2026-12-31 00:00', '2026-09-14 20:00', '2026-09-14 21:00');
        $latest = $this->override(2, 51, 'permanent', '2026-09-14 12:00', '2026-12-31 00:00', '2026-09-16 13:00', '2026-09-16 14:00');

        $resolution = $this->resolver->resolve([$event], [$older, $latest], $from, $to);

        $this->assertCount(1, $resolution->occurrences);
        $this->assertSame('2026-09-16T13:00:00.000000Z', $resolution->occurrences[0]->effectiveStart->toISOString());

        $shuffled = $this->resolver->resolve([$event], [$latest, $older], $from, $to);
        $this->assertSame(
            $resolution->occurrences[0]->toArray(),
            $shuffled->occurrences[0]->toArray(),
            'Override input order must not affect resolution.',
        );
    }

    public function test_shift_tie_breaks_by_greatest_override_id(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-09-07 09:00', '2026-09-07 10:00', 'FREQ=WEEKLY');
        [$from, $to] = self::dayWindow('2026-09-16', '2026-09-16');
        $first = $this->override(1, 51, 'permanent', '2026-09-14 00:00', '2026-12-31 00:00', '2026-09-16 08:00', '2026-09-16 09:00');
        $second = $this->override(2, 51, 'permanent', '2026-09-14 00:00', '2026-12-31 00:00', '2026-09-16 18:00', '2026-09-16 19:00');

        $resolution = $this->resolver->resolve([$event], [$second, $first], $from, $to);

        $this->assertSame('shifted:2', $resolution->occurrences[0]->provenance->value);
    }

    public function test_shifted_occurrence_appears_on_the_effective_date_not_the_original_date(): void
    {
        // Regression (ES-IMPL-04): a covered occurrence re-timed onto a
        // different calendar day must resolve in the EFFECTIVE day's window
        // and disappear from the original day's window.
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-09-07 09:00', '2026-09-07 10:00', 'FREQ=WEEKLY');
        $shift = $this->override(2, 51, 'permanent', '2026-09-14 00:00', '2026-12-31 00:00', '2026-09-16 13:00', '2026-09-16 14:00');

        [$originalFrom, $originalTo] = self::dayWindow('2026-09-14', '2026-09-14');
        $this->assertCount(
            0,
            $this->resolver->resolve([$event], [$shift], $originalFrom, $originalTo)->occurrences,
            'The original date is vacated by the shift.',
        );

        [$effectiveFrom, $effectiveTo] = self::dayWindow('2026-09-16', '2026-09-16');
        $effective = $this->resolver->resolve([$event], [$shift], $effectiveFrom, $effectiveTo);
        $this->assertCount(1, $effective->occurrences);
        $this->assertSame('shifted:2', $effective->occurrences[0]->provenance->value);
        $this->assertSame('2026-09-14', $effective->occurrences[0]->originalStart->toDateString());
    }

    public function test_override_targeting_identity_is_stable_across_windows(): void
    {
        $event = self::event(51, HardLandscapeType::RECURRING, '2026-08-17 09:00', '2026-08-17 10:00', 'FREQ=WEEKLY');
        $exception = $this->override(3, 51, 'one_time', '2026-08-24 00:00', '2026-08-24 00:00', '2026-08-24 15:00', '2026-08-24 16:00');
        [$weekFrom, $weekTo] = self::dayWindow('2026-08-24', '2026-08-24');
        [$monthFrom, $monthTo] = self::dayWindow('2026-08-01', '2026-08-31');

        $fromWeek = $this->resolver->resolve([$event], [$exception], $weekFrom, $weekTo)->occurrences[0];
        $fromMonth = $this->resolver->resolve([$event], [$exception], $monthFrom, $monthTo)->occurrences[1];

        $this->assertSame($fromWeek->identity(), $fromMonth->identity());
        $this->assertSame($fromWeek->provenance->value, $fromMonth->provenance->value);
    }

    public function test_provenance_reserved_values_follow_the_adr_string_contract(): void
    {
        $this->assertSame('base', OccurrenceProvenance::base()->value);
        $this->assertSame('shifted:7', OccurrenceProvenance::shifted(7)->value);
        $this->assertSame('excepted:7', OccurrenceProvenance::excepted(7)->value);
        $this->assertSame('cancelled:7', OccurrenceProvenance::cancelled(7)->value);

        $this->expectException(InvalidArgumentException::class);
        new OccurrenceProvenance('shifted');
    }
}
