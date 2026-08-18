<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\CandidatePlacement;
use App\Domain\Scheduling\HardConstraintEngine;
use App\Domain\Scheduling\ScheduleContext;
use App\Domain\Scheduling\ValueObjects\Deadline;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HardConstraintEngineTest extends TestCase
{
    private TimeRange $day;

    private TimeRange $landscape;

    protected function setUp(): void
    {
        $this->day = TimeRange::from('2026-08-19T00:00:00', '2026-08-20T00:00:00');
        $this->landscape = TimeRange::from('2026-08-19T08:00:00', '2026-08-19T10:00:00');
    }

    private function candidate(
        string $start,
        string $end,
        int $duration = 60,
        array $overrides = [],
    ): CandidatePlacement {
        return new CandidatePlacement(
            taskId: $overrides['taskId'] ?? 'task-1',
            title: $overrides['title'] ?? 'Write report',
            durationMinutes: $duration,
            slot: TimeRange::from($start, $end),
            deadline: $overrides['deadline'] ?? null,
            isLocked: $overrides['isLocked'] ?? false,
            isSacredAnchor: $overrides['isSacredAnchor'] ?? false,
            existingSlot: $overrides['existingSlot'] ?? null,
        );
    }

    private function engine(): HardConstraintEngine
    {
        return HardConstraintEngine::default();
    }

    #[Test]
    public function feasible_candidate_has_no_violations(): void
    {
        $context = new ScheduleContext($this->day, [$this->landscape]);
        $candidate = $this->candidate('2026-08-19T14:00:00', '2026-08-19T15:00:00');

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
        $this->assertTrue($this->engine()->isFeasible($context, [$candidate]));
    }

    #[Test]
    public function hard_landscape_collision_is_rejected(): void
    {
        $context = new ScheduleContext($this->day, [$this->landscape]);
        $candidate = $this->candidate('2026-08-19T09:00:00', '2026-08-19T10:30:00');

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertCount(1, $violations);
        $this->assertSame('HARD_LANDSCAPE_COLLISION', $violations[0]->ruleCode);
    }

    #[Test]
    public function locked_task_must_not_be_moved_by_automation(): void
    {
        $context = new ScheduleContext($this->day);
        $existing = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $candidate = $this->candidate(
            '2026-08-19T14:00:00',
            '2026-08-19T15:00:00',
            overrides: ['isLocked' => true, 'existingSlot' => $existing],
        );

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertSame('LOCKED_TASK_MOVE', $violations[0]->ruleCode);
    }

    #[Test]
    public function locked_task_at_same_slot_is_not_a_move(): void
    {
        $context = new ScheduleContext($this->day);
        $existing = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $candidate = $this->candidate(
            '2026-08-19T09:00:00',
            '2026-08-19T10:00:00',
            overrides: ['isLocked' => true, 'existingSlot' => $existing],
        );

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
    }

    #[Test]
    public function sacred_anchor_must_be_25_minutes_at_or_after_0600_and_locked(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate(
            '2026-08-19T05:00:00',
            '2026-08-19T05:25:00',
            duration: 25,
            overrides: ['isSacredAnchor' => true],
        );

        $violations = $this->engine()->validate($context, [$candidate]);

        $codes = array_map(static fn ($v) => $v->ruleCode, $violations);
        $this->assertContains('SACRED_ANCHOR_VIOLATION', $codes);
    }

    #[Test]
    public function valid_sacred_anchor_passes(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate(
            '2026-08-19T06:00:00',
            '2026-08-19T06:25:00',
            duration: 25,
            overrides: ['isSacredAnchor' => true, 'isLocked' => true],
        );

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
    }

    #[Test]
    public function slot_outside_horizon_is_invalid(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate('2026-08-20T00:00:00', '2026-08-20T01:00:00');

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertSame('TEMPORAL_VALIDITY', $violations[0]->ruleCode);
    }

    #[Test]
    public function slot_ending_after_deadline_is_infeasible(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate(
            '2026-08-19T14:00:00',
            '2026-08-19T15:00:00',
            overrides: ['deadline' => new Deadline(new CarbonImmutable('2026-08-19T14:30:00'))],
        );

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertSame('DEADLINE_FEASIBILITY', $violations[0]->ruleCode);
    }

    #[Test]
    public function slot_before_deadline_is_feasible(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate(
            '2026-08-19T14:00:00',
            '2026-08-19T15:00:00',
            overrides: ['deadline' => new Deadline(new CarbonImmutable('2026-08-19T16:00:00'))],
        );

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
    }

    #[Test]
    public function task_longer_than_slot_does_not_fit(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate('2026-08-19T14:00:00', '2026-08-19T15:00:00', duration: 90);

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertSame('DURATION_FIT', $violations[0]->ruleCode);
    }

    #[Test]
    public function task_matching_slot_duration_fits(): void
    {
        $context = new ScheduleContext($this->day);
        $candidate = $this->candidate('2026-08-19T14:00:00', '2026-08-19T15:00:00', duration: 60);

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
    }

    #[Test]
    public function overlap_with_existing_assignment_is_rejected(): void
    {
        $existing = TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $context = new ScheduleContext($this->day, existingAssignments: [$existing]);
        $candidate = $this->candidate('2026-08-19T09:30:00', '2026-08-19T10:30:00');

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertSame('ILLEGAL_OVERLAP', $violations[0]->ruleCode);
    }

    #[Test]
    public function overlap_between_candidates_is_rejected(): void
    {
        $context = new ScheduleContext($this->day);
        $a = $this->candidate('2026-08-19T09:00:00', '2026-08-19T10:00:00', overrides: ['taskId' => 'a']);
        $b = $this->candidate('2026-08-19T09:30:00', '2026-08-19T10:30:00', overrides: ['taskId' => 'b']);

        $violations = $this->engine()->validate($context, [$a, $b]);

        $this->assertContains('ILLEGAL_OVERLAP', array_map(static fn ($v) => $v->ruleCode, $violations));
    }

    #[Test]
    public function safety_reserve_rejects_over_booking(): void
    {
        $context = new ScheduleContext(
            $this->day,
            reservePercent: 30,
        );
        $candidate = $this->candidate('2026-08-19T00:00:00', '2026-08-19T20:00:00', duration: 1200);

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertContains('SAFETY_RESERVE', array_map(static fn ($v) => $v->ruleCode, $violations));
    }

    #[Test]
    public function safety_reserve_allows_within_limit(): void
    {
        $context = new ScheduleContext(
            $this->day,
            reservePercent: 30,
        );
        $candidate = $this->candidate('2026-08-19T09:00:00', '2026-08-19T16:00:00', duration: 420);

        $this->assertSame([], $this->engine()->validate($context, [$candidate]));
    }

    #[Test]
    public function soft_scoring_cannot_make_invalid_candidate_executable(): void
    {
        $context = new ScheduleContext($this->day, [$this->landscape]);
        $candidate = $this->candidate('2026-08-19T09:00:00', '2026-08-19T10:30:00');

        $violations = $this->engine()->validate($context, [$candidate]);

        $this->assertNotEmpty($violations);
        $this->assertSame(['HARD_LANDSCAPE_COLLISION'], array_map(static fn ($v) => $v->ruleCode, $violations));
    }
}
