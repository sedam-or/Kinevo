<?php

namespace Tests\Unit;

use App\Domain\Adaptive\ContextFitScorer;
use App\Domain\Adaptive\ContextFitService;
use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use App\Domain\Scheduling\ScheduleTask;
use App\Domain\Scheduling\ValueObjects\PriorityTier;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContextFitServiceTest extends TestCase
{
    private ContextFitService $service;

    protected function setUp(): void
    {
        $this->service = new ContextFitService(new ContextFitScorer);
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
        return new ScheduleTask($id, "Task {$id}", 30, PriorityTier::p3());
    }

    #[Test]
    public function fit_map_aggregates_user_and_task_signals(): void
    {
        $observations = [
            $this->observation(['task_id' => 42, 'energy' => 8, 'difficulty' => 8]),
            $this->observation(['task_id' => 42, 'energy' => 6, 'difficulty' => 6, 'familiarity' => 4]),
            $this->observation(['stress' => 9]),
            $this->observation(['stress' => 7]),
        ];

        $map = $this->service->fitMap($observations, [42]);

        // energy 0.7, stress 0.8, difficulty 0.7, familiarity 0.4
        // energyFit = 0.5; 0.5*0.5 + 0.3*0.4 + 0.2*0.2 = 0.41
        $this->assertEqualsWithDelta(0.41, $map['42'], 0.001);
    }

    #[Test]
    public function sparse_user_energy_falls_back_to_neutral(): void
    {
        // Only one energy sample (< MIN_USER_SAMPLES): energy is treated as
        // sparse, so difficulty cannot penalize the fit (FR-59 baseline policy).
        $observations = [
            $this->observation(['task_id' => 7, 'energy' => 2, 'difficulty' => 9]),
        ];

        $map = $this->service->fitMap($observations, [7]);

        $this->assertEqualsWithDelta(ContextFitScorer::BASELINE, $map['7'], 0.001);
    }

    #[Test]
    public function task_without_observations_gets_baseline(): void
    {
        $observations = [
            $this->observation(['energy' => 5]),
            $this->observation(['energy' => 5]),
        ];

        $map = $this->service->fitMap($observations, [99]);

        $this->assertEqualsWithDelta(ContextFitScorer::BASELINE, $map['99'], 0.001);
    }

    #[Test]
    public function apply_to_schedule_tasks_sets_context_fit(): void
    {
        $tasks = [$this->scheduleTask('a'), $this->scheduleTask('b')];
        $map = ['a' => 0.9];

        $applied = $this->service->applyToScheduleTasks($tasks, $map);

        $this->assertSame(0.9, $applied[0]->contextFit);
        $this->assertNull($applied[1]->contextFit);
        $this->assertNotSame($tasks[0], $applied[0]);
    }

    #[Test]
    public function fit_map_is_deterministic(): void
    {
        $observations = [
            $this->observation(['task_id' => 5, 'energy' => 7, 'difficulty' => 6]),
            $this->observation(['task_id' => 5, 'energy' => 5, 'familiarity' => 8]),
        ];

        $this->assertSame(
            $this->service->fitMap($observations, [5]),
            $this->service->fitMap($observations, [5]),
        );
    }
}
