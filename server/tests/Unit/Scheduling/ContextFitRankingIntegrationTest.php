<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Adaptive\ContextFitScorer;
use App\Domain\Adaptive\ContextFitService;
use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use App\Domain\Scheduling\RankingCandidate;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\TaskRankingEngine;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * FR-59 AC integration: a high-difficulty task with low(er) energy can receive
 * a lower soft ranking — but the fit signal never touches hard constraints.
 */
class ContextFitRankingIntegrationTest extends TestCase
{
    private ContextFitService $service;

    private TaskRankingEngine $engine;

    protected function setUp(): void
    {
        $this->service = new ContextFitService(new ContextFitScorer);
        $this->engine = TaskRankingEngine::default();
    }

    private function observation(array $signals): ContextObservation
    {
        return ContextObservation::create(
            1,
            taskId: $signals['task_id'] ?? null,
            energy: isset($signals['energy']) ? SignalLevel::fromInt($signals['energy']) : null,
            stress: isset($signals['stress']) ? SignalLevel::fromInt($signals['stress']) : null,
            difficulty: isset($signals['difficulty']) ? SignalLevel::fromInt($signals['difficulty']) : null,
            familiarity: isset($signals['familiarity']) ? SignalLevel::fromInt($signals['familiarity']) : null,
        );
    }

    private function scheduleTask(string $id): ScheduleTask
    {
        // Identical soft signals except contextFit, so context fit is the only
        // distinguishing ranking component.
        return new ScheduleTask(
            $id,
            "Task {$id}",
            30,
            PriorityTier::p3(),
        );
    }

    #[Test]
    public function high_difficulty_task_with_limited_energy_ranks_lower(): void
    {
        // Same user, moderate energy (5/5 → 0.5). Difficulty differentiates.
        $observations = [
            $this->observation(['energy' => 5]),
            $this->observation(['energy' => 5]),
            $this->observation(['task_id' => 1, 'difficulty' => 9]),
            $this->observation(['task_id' => 2, 'difficulty' => 1]),
        ];

        $tasks = [$this->scheduleTask('1'), $this->scheduleTask('2')];
        $tasks = $this->service->applyToScheduleTasks($tasks, $this->service->fitMap($observations, [1, 2]));

        $this->assertLessThan($tasks[1]->contextFit, $tasks[0]->contextFit);

        $ranked = $this->engine->rank(array_map(
            fn (ScheduleTask $t) => new RankingCandidate(
                taskId: $t->taskId,
                priorityTier: $t->priorityTier,
                contextFit: $t->contextFit,
                estimatedMinutes: $t->durationMinutes,
            ),
            $tasks,
        ));

        // Easy task (good fit) is ranked before the hard task (poor fit).
        $this->assertSame(['2', '1'], array_map(static fn (RankingCandidate $c) => $c->taskId, $ranked));
    }

    #[Test]
    public function hard_constraints_are_untouched_by_context_fit(): void
    {
        // Context fit only changes soft ordering; locked/priority/deadline
        // signals are not modified by the fit map application.
        $lockedHighPriority = new ScheduleTask(
            'locked',
            'Locked',
            30,
            PriorityTier::p1(),
            isLocked: true,
        );

        $tasks = $this->service->applyToScheduleTasks(
            [$lockedHighPriority],
            ['locked' => 0.0],
        );

        $this->assertSame(0.0, $tasks[0]->contextFit);
        $this->assertTrue($tasks[0]->isLocked);
        $this->assertTrue($tasks[0]->priorityTier->equals(PriorityTier::p1()));
    }
}
