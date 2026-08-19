<?php

namespace App\Domain\Adaptive;

/**
 * Deterministic burnout warning heuristic over recent context check-ins
 * (TASK-060, FR-49 upstream). Sustained high stress with low energy raises a
 * warning that suppresses aggressive capacity boosts. The rule is advisory —
 * sparse data never triggers the signal, and no claim of clinical validity is
 * made (FR-58 Business Rule).
 */
final class BurnoutSignalDetector
{
    public const MIN_SAMPLES = 3;

    public const STRESS_THRESHOLD = 7;

    public const ENERGY_THRESHOLD = 4;

    /**
     * @param  array<int, ContextObservation>  $observations  recent window, newest first
     */
    public function detect(array $observations): BurnoutSignal
    {
        $withStress = array_values(array_filter(
            $observations,
            static fn (ContextObservation $o) => $o->stress !== null,
        ));

        $withEnergy = array_values(array_filter(
            $observations,
            static fn (ContextObservation $o) => $o->energy !== null,
        ));

        if (count($withStress) < self::MIN_SAMPLES || count($withEnergy) < self::MIN_SAMPLES) {
            return new BurnoutSignal(
                false,
                'Insufficient energy/stress history; no burnout signal.',
                min(count($withStress), count($withEnergy)),
            );
        }

        $avgStress = array_sum(array_map(
            static fn (ContextObservation $o) => $o->stress->value,
            $withStress,
        )) / count($withStress);

        $avgEnergy = array_sum(array_map(
            static fn (ContextObservation $o) => $o->energy->value,
            $withEnergy,
        )) / count($withEnergy);

        if ($avgStress >= self::STRESS_THRESHOLD && $avgEnergy <= self::ENERGY_THRESHOLD) {
            return new BurnoutSignal(
                true,
                sprintf(
                    'Sustained high stress (avg %.1f/10) with low energy (avg %.1f/10); boost suppressed.',
                    $avgStress,
                    $avgEnergy,
                ),
                min(count($withStress), count($withEnergy)),
            );
        }

        return new BurnoutSignal(
            false,
            sprintf(
                'No burnout signal: avg stress %.1f/10, avg energy %.1f/10.',
                $avgStress,
                $avgEnergy,
            ),
            min(count($withStress), count($withEnergy)),
        );
    }
}
