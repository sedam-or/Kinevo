<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\DraftAssignment;
use App\Domain\Scheduling\ExplanationReason;
use App\Domain\Scheduling\RankingCandidate;
use App\Domain\Scheduling\ReasonMapper;
use App\Domain\Scheduling\SchedulerExplainer;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SchedulerExplainerTest extends TestCase
{
    private SchedulerExplainer $explainer;

    private ReasonMapper $mapper;

    protected function setUp(): void
    {
        $this->explainer = new SchedulerExplainer;
        $this->mapper = new ReasonMapper;
    }

    private function task(string $id, array $overrides = []): ScheduleTask
    {
        return new ScheduleTask(
            taskId: $id,
            title: $overrides['title'] ?? "Task {$id}",
            durationMinutes: $overrides['durationMinutes'] ?? 60,
            priorityTier: $overrides['priorityTier'] ?? PriorityTier::p3(),
            taskDeadline: $overrides['taskDeadline'] ?? null,
            goalDeadline: $overrides['goalDeadline'] ?? null,
            milestoneDeadline: $overrides['milestoneDeadline'] ?? null,
            progress: $overrides['progress'] ?? 0,
            contextFit: $overrides['contextFit'] ?? null,
            fragmentationPenalty: $overrides['fragmentationPenalty'] ?? 0.0,
            continuityPreference: $overrides['continuityPreference'] ?? false,
            isLocked: $overrides['isLocked'] ?? false,
            isSacredAnchor: $overrides['isSacredAnchor'] ?? false,
        );
    }

    private function candidate(ScheduleTask $task, array $overrides = []): RankingCandidate
    {
        return new RankingCandidate(
            taskId: $task->taskId,
            priorityTier: $task->priorityTier,
            goalDeadline: $task->goalDeadline,
            milestoneDeadline: $task->milestoneDeadline,
            taskDeadline: $task->taskDeadline,
            progress: $task->progress,
            contextFit: $task->contextFit,
            fragmentationPenalty: $task->fragmentationPenalty,
            slot: $overrides['slot'] ?? TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            continuityPreference: $task->continuityPreference,
            estimatedMinutes: $task->durationMinutes,
        );
    }

    #[Test]
    public function locked_and_sacred_anchor_tasks_get_protection_reasons(): void
    {
        $task = $this->task('t1', ['isLocked' => true, 'isSacredAnchor' => true]);
        $candidate = $this->candidate($task);

        $codes = array_map(
            static fn (ExplanationReason $r) => $r->code,
            $this->mapper->reasons($task, $candidate),
        );

        $this->assertContains(ExplanationReason::LOCK_PROTECTED, $codes);
        $this->assertContains(ExplanationReason::SACRED_ANCHOR, $codes);
    }

    #[Test]
    public function near_deadline_adds_deadline_priority(): void
    {
        $task = $this->task('t1', ['taskDeadline' => CarbonImmutable::parse('2026-08-20')]);
        $candidate = $this->candidate($task);

        $codes = array_map(
            static fn (ExplanationReason $r) => $r->code,
            $this->mapper->reasons($task, $candidate),
        );

        $this->assertContains(ExplanationReason::DEADLINE_PRIORITY, $codes);
    }

    #[Test]
    public function high_context_fit_adds_energy_fit(): void
    {
        $task = $this->task('t1', ['contextFit' => 0.9]);
        $candidate = $this->candidate($task);

        $codes = array_map(
            static fn (ExplanationReason $r) => $r->code,
            $this->mapper->reasons($task, $candidate),
        );

        $this->assertContains(ExplanationReason::ENERGY_FIT, $codes);
    }

    #[Test]
    public function high_progress_adds_progress_value(): void
    {
        $task = $this->task('t1', ['progress' => 90]);
        $candidate = $this->candidate($task);

        $codes = array_map(
            static fn (ExplanationReason $r) => $r->code,
            $this->mapper->reasons($task, $candidate),
        );

        $this->assertContains(ExplanationReason::PROGRESS_VALUE, $codes);
    }

    #[Test]
    public function fragmentation_and_continuity_add_soft_reasons(): void
    {
        $task = $this->task('t1', [
            'fragmentationPenalty' => 0.6,
            'continuityPreference' => true,
        ]);
        $candidate = $this->candidate($task);

        $codes = array_map(
            static fn (ExplanationReason $r) => $r->code,
            $this->mapper->reasons($task, $candidate),
        );

        $this->assertContains(ExplanationReason::CONTEXT_SWITCH_PENALTY, $codes);
        $this->assertContains(ExplanationReason::CONTINUITY_PREFERENCE, $codes);
    }

    #[Test]
    public function explanation_contains_readable_summary_and_context(): void
    {
        $task = $this->task('t1', ['taskDeadline' => CarbonImmutable::parse('2026-08-20')]);
        $candidate = $this->candidate($task);
        $assignment = new DraftAssignment(
            't1',
            $task->title,
            $candidate->slot,
        );

        $explanation = $this->explainer->explain(
            $assignment,
            $candidate,
            $this->mapper->reasons($task, $candidate),
            '',
        );

        $this->assertStringContainsString('Placed "Task t1"', $explanation->summary);
        $this->assertSame('tier-3', $explanation->primaryPriority);
        $this->assertNotNull($explanation->deadlinePressure);
        $this->assertStringContainsString('slot 60 min', $explanation->capacityContext);
        $this->assertContains('DEADLINE_FEASIBILITY', $explanation->acceptedConstraints);
    }

    #[Test]
    public function rejected_alternatives_reflect_constraint_violations(): void
    {
        $task = $this->task('t1');
        $candidate = $this->candidate($task);
        $assignment = new DraftAssignment('t1', $task->title, $candidate->slot);

        $explanation = $this->explainer->explain(
            $assignment,
            $candidate,
            $this->mapper->reasons($task, $candidate),
            'HARD_LANDSCAPE_COLLISION for alternative slot',
        );

        $this->assertContains('HARD_LANDSCAPE_COLLISION for alternative slot', $explanation->rejectedAlternatives);
    }

    #[Test]
    public function no_reasons_produces_simple_summary(): void
    {
        $task = $this->task('t1');
        $candidate = $this->candidate($task);
        $assignment = new DraftAssignment('t1', $task->title, $candidate->slot);

        $explanation = $this->explainer->explain($assignment, $candidate, [], '');

        $this->assertSame('Placed "Task t1" at 2026-08-19 09:00:00.', $explanation->summary);
    }

    #[Test]
    public function reason_code_validation_rejects_unknown_codes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ExplanationReason('NOT_A_REASON');
    }

    #[Test]
    public function reason_label_is_stable(): void
    {
        $reason = new ExplanationReason(ExplanationReason::CAPACITY_FIT);

        $this->assertSame('Task fits available slot capacity', $reason->label());
    }

    #[Test]
    public function explanation_is_deterministic(): void
    {
        $task = $this->task('t1', ['isLocked' => true, 'progress' => 90]);
        $candidate = $this->candidate($task);
        $assignment = new DraftAssignment('t1', $task->title, $candidate->slot);

        $first = $this->explainer->explain($assignment, $candidate, $this->mapper->reasons($task, $candidate), '');
        $second = $this->explainer->explain($assignment, $candidate, $this->mapper->reasons($task, $candidate), '');

        $this->assertSame($first->summary, $second->summary);
        $this->assertSame(
            array_map(static fn (ExplanationReason $r) => $r->code, $first->reasons),
            array_map(static fn (ExplanationReason $r) => $r->code, $second->reasons),
        );
    }
}
