<?php

namespace App\Domain\Adaptive;

/**
 * Deterministic soft context-fit scorer (FR-59, TASK-061). Converts adaptive
 * context signals (energy, stress, task difficulty, skill familiarity) into a
 * single 0..1 fit used by the soft ranking layer (context_fit_score, ranking
 * #6). Higher is a better fit.
 *
 * Sparse/anomalous data falls back to the neutral baseline (0.5) per component
 * (FR-59 Business Rule), keeping the score deterministic and explainable.
 *
 * Signals are subjective and advisory only — never clinical measurements.
 */
final class ContextFitScorer
{
    public const BASELINE = 0.5;

    /**
     * @param  float|null  $energy  avg user energy normalized 0..1 (null = sparse)
     * @param  float|null  $stress  avg user stress normalized 0..1 (null = sparse)
     * @param  float|null  $difficulty  task difficulty 0..1 (null = unknown)
     * @param  float|null  $familiarity  task familiarity 0..1 (null = unknown)
     */
    public function score(
        ?float $energy = null,
        ?float $stress = null,
        ?float $difficulty = null,
        ?float $familiarity = null,
    ): float {
        // Energy fit: high energy + low difficulty is a strong fit; a difficult
        // task with low energy is a poor fit right now (FR-59 AC). Needs both
        // signals — otherwise neutral (deterministic baseline policy).
        $energyFit = ($energy !== null && $difficulty !== null)
            ? $this->clamp01(self::BASELINE + $this->clamp01($energy) - $this->clamp01($difficulty))
            : self::BASELINE;

        $familiarityFit = $familiarity !== null ? $this->clamp01($familiarity) : self::BASELINE;

        // High stress lowers current fit; unknown stress is neutral.
        $calmness = $stress !== null ? $this->clamp01(1 - $this->clamp01($stress)) : self::BASELINE;

        return $this->clamp01(
            0.5 * $energyFit + 0.3 * $familiarityFit + 0.2 * $calmness,
        );
    }

    private function clamp01(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
