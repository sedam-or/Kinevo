<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\RankingCandidate;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaskRankingEngineTest extends TestCase
{
    private TaskRankingEngine $engine;

    protected function setUp(): void
    {
        $this->engine = TaskRankingEngine::default();
    }

    private function candidate(string $taskId, array $overrides = []): RankingCandidate
    {
        return new RankingCandidate(
            taskId: $taskId,
            priorityTier: $overrides['priorityTier'] ?? PriorityTier::p3(),
            goalDeadline: $overrides['goalDeadline'] ?? null,
            milestoneDeadline: $overrides['milestoneDeadline'] ?? null,
            taskDeadline: $overrides['taskDeadline'] ?? null,
            progress: $overrides['progress'] ?? 0,
            contextFit: $overrides['contextFit'] ?? null,
            fragmentationPenalty: $overrides['fragmentationPenalty'] ?? 0.0,
            slot: $overrides['slot'] ?? null,
            continuityPreference: $overrides['continuityPreference'] ?? false,
            estimatedMinutes: $overrides['estimatedMinutes'] ?? null,
        );
    }

    private function rankIds(array $candidates): array
    {
        return array_map(
            static fn (RankingCandidate $c) => $c->taskId,
            $this->engine->rank($candidates),
        );
    }

    #[Test]
    public function priority_tier_1_outranks_tier_3(): void
    {
        $high = $this->candidate('high', ['priorityTier' => PriorityTier::p1()]);
        $low = $this->candidate('low', ['priorityTier' => PriorityTier::p3()]);

        $this->assertSame(['high', 'low'], $this->rankIds([$low, $high]));
    }

    #[Test]
    public function equal_tier_uses_nearest_goal_deadline(): void
    {
        $near = $this->candidate(
            'near',
            ['goalDeadline' => CarbonImmutable::parse('2026-08-25')],
        );
        $far = $this->candidate(
            'far',
            ['goalDeadline' => CarbonImmutable::parse('2026-12-31')],
        );

        $this->assertSame(['near', 'far'], $this->rankIds([$far, $near]));
    }

    #[Test]
    public function no_goal_deadline_ranks_after_any_deadline(): void
    {
        $none = $this->candidate('none');
        $near = $this->candidate('near', ['goalDeadline' => CarbonImmutable::parse('2026-08-25')]);

        $this->assertSame(['near', 'none'], $this->rankIds([$none, $near]));
    }

    #[Test]
    public function nearest_milestone_deadline_wins_at_equal_tier_and_goal(): void
    {
        $near = $this->candidate('near', [
            'milestoneDeadline' => CarbonImmutable::parse('2026-08-20'),
        ]);
        $far = $this->candidate('far', [
            'milestoneDeadline' => CarbonImmutable::parse('2026-09-30'),
        ]);

        $this->assertSame(['near', 'far'], $this->rankIds([$far, $near]));
    }

    #[Test]
    public function nearest_task_deadline_wins_at_tier_goal_and_milestone_tie(): void
    {
        $near = $this->candidate('near', [
            'taskDeadline' => CarbonImmutable::parse('2026-08-22'),
        ]);
        $far = $this->candidate('far', [
            'taskDeadline' => CarbonImmutable::parse('2026-08-28'),
        ]);

        $this->assertSame(['near', 'far'], $this->rankIds([$far, $near]));
    }

    #[Test]
    public function higher_progress_leverage_ranks_first(): void
    {
        $done = $this->candidate('done', ['progress' => 90]);
        $fresh = $this->candidate('fresh', ['progress' => 10]);

        $this->assertSame(['done', 'fresh'], $this->rankIds([$fresh, $done]));
    }

    #[Test]
    public function context_fit_breaks_all_earlier_ties(): void
    {
        $good = $this->candidate('good', ['contextFit' => 0.9]);
        $bad = $this->candidate('bad', ['contextFit' => 0.2]);

        $this->assertSame(['good', 'bad'], $this->rankIds([$bad, $good]));
    }

    #[Test]
    public function higher_fragmentation_penalty_ranks_first(): void
    {
        $big = $this->candidate('big', ['fragmentationPenalty' => 0.8]);
        $small = $this->candidate('small', ['fragmentationPenalty' => 0.1]);

        $this->assertSame(['big', 'small'], $this->rankIds([$small, $big]));
    }

    #[Test]
    public function exact_duration_fit_beats_partial_fit(): void
    {
        $exact = $this->candidate('exact', [
            'slot' => TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            'estimatedMinutes' => 60,
        ]);
        $partial = $this->candidate('partial', [
            'slot' => TimeRange::from('2026-08-19T09:00:00', '2026-08-19T10:00:00'),
            'estimatedMinutes' => 30,
        ]);

        $this->assertSame(['exact', 'partial'], $this->rankIds([$partial, $exact]));
    }

    #[Test]
    public function continuity_preference_breaks_final_tie(): void
    {
        $continuing = $this->candidate('continuing', ['continuityPreference' => true]);
        $new = $this->candidate('new');

        $this->assertSame(['continuing', 'new'], $this->rankIds([$new, $continuing]));
    }

    #[Test]
    public function identical_candidates_are_stable(): void
    {
        $a = $this->candidate('a');
        $b = $this->candidate('b');

        $this->assertSame(['a', 'b'], $this->rankIds([$a, $b]));
    }

    #[Test]
    public function full_lexicographic_chain_orders_correctly(): void
    {
        $candidates = [
            $this->candidate('t3-far', ['priorityTier' => PriorityTier::p3(), 'goalDeadline' => CarbonImmutable::parse('2026-12-31')]),
            $this->candidate('t1', ['priorityTier' => PriorityTier::p1()]),
            $this->candidate('t2-near', ['priorityTier' => PriorityTier::p2(), 'goalDeadline' => CarbonImmutable::parse('2026-08-25')]),
            $this->candidate('t2-far', ['priorityTier' => PriorityTier::p2(), 'goalDeadline' => CarbonImmutable::parse('2026-09-30')]),
        ];

        $this->assertSame(['t1', 't2-near', 't2-far', 't3-far'], $this->rankIds($candidates));
    }

    #[Test]
    public function component_scores_are_observable_for_explanations(): void
    {
        $candidate = $this->candidate('c', [
            'priorityTier' => PriorityTier::p1(),
            'goalDeadline' => CarbonImmutable::parse('2026-08-25'),
        ]);

        $ranked = $this->engine->score($candidate);

        $this->assertSame(3.0, $ranked->components['priority_score']);
        $this->assertArrayHasKey('goal_deadline_score', $ranked->components);
        $this->assertArrayHasKey('milestone_score', $ranked->components);
        $this->assertArrayHasKey('task_deadline_score', $ranked->components);
    }
}
