<?php

namespace App\Domain\Adaptive;

use App\Domain\Scheduling\ScheduleTask;

/**
 * Aggregates context check-ins into per-task soft context-fit signals and
 * applies them to the scheduling input (FR-59, TASK-061).
 *
 * User-level energy/stress require at least MIN_USER_SAMPLES recent check-ins;
 * otherwise the component is treated as sparse and falls back to the neutral
 * baseline. Task-scoped difficulty/familiarity use any available sample for
 * that task. Hard constraints are never touched — this feeds ranking only.
 */
final class ContextFitService
{
    public const MIN_USER_SAMPLES = 2;

    public function __construct(
        private readonly ContextFitScorer $scorer,
    ) {}

    /**
     * Build a taskId → context-fit map over the given observations.
     *
     * @param  array<int, ContextObservation>  $observations
     * @param  array<int, int>  $taskIds
     * @return array<string, float>
     */
    public function fitMap(array $observations, array $taskIds): array
    {
        $energy = $this->userEnergy($observations);
        $stress = $this->userStress($observations);

        /** @var array<string, float> $map */
        $map = [];
        foreach ($taskIds as $taskId) {
            $map[(string) $taskId] = $this->scorer->score(
                energy: $energy,
                stress: $stress,
                difficulty: $this->taskDifficulty($observations, $taskId),
                familiarity: $this->taskFamiliarity($observations, $taskId),
            );
        }

        return $map;
    }

    /**
     * Rebuild schedule tasks with contextFit populated from the map. Tasks not
     * present in the map keep their current signal (or null = neutral).
     *
     * @param  array<int, ScheduleTask>  $scheduleTasks
     * @param  array<string, float>  $fitMap
     * @return array<int, ScheduleTask>
     */
    public function applyToScheduleTasks(array $scheduleTasks, array $fitMap): array
    {
        return array_map(
            static fn (ScheduleTask $task) => isset($fitMap[$task->taskId])
                ? $task->withContextFit($fitMap[$task->taskId])
                : $task,
            $scheduleTasks,
        );
    }

    /**
     * @param  array<int, ContextObservation>  $observations
     */
    private function userEnergy(array $observations): ?float
    {
        return $this->average($observations, static fn (ContextObservation $o) => $o->energy?->value, self::MIN_USER_SAMPLES);
    }

    /**
     * @param  array<int, ContextObservation>  $observations
     */
    private function userStress(array $observations): ?float
    {
        return $this->average($observations, static fn (ContextObservation $o) => $o->stress?->value, self::MIN_USER_SAMPLES);
    }

    /**
     * @param  array<int, ContextObservation>  $observations
     */
    private function taskDifficulty(array $observations, int $taskId): ?float
    {
        return $this->averageForTask($observations, $taskId, static fn (ContextObservation $o) => $o->difficulty?->value, 1);
    }

    /**
     * @param  array<int, ContextObservation>  $observations
     */
    private function taskFamiliarity(array $observations, int $taskId): ?float
    {
        return $this->averageForTask($observations, $taskId, static fn (ContextObservation $o) => $o->familiarity?->value, 1);
    }

    /**
     * @param  array<int, ContextObservation>  $observations
     */
    private function averageForTask(
        array $observations,
        int $taskId,
        callable $value,
        int $minSamples,
    ): ?float {
        $taskObservations = array_values(array_filter(
            $observations,
            static fn (ContextObservation $o) => $o->taskId === $taskId,
        ));

        return $this->average($taskObservations, $value, $minSamples);
    }

    /**
     * Average a 1–10 signal normalized to 0..1, or null when too few samples.
     *
     * @param  array<int, ContextObservation>  $observations
     */
    private function average(array $observations, callable $value, int $minSamples): ?float
    {
        $values = array_values(array_filter(
            array_map($value, $observations),
            static fn ($level) => $level !== null,
        ));

        if (count($values) < $minSamples) {
            return null;
        }

        return array_sum($values) / count($values) / 10;
    }
}
