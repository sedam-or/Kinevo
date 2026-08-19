<?php

namespace App\Domain\Focus;

/**
 * Deterministic focus block recommendation (SRS §12.4, TASK-062).
 *
 * Recommends a focus duration from recent completed focus sessions:
 * - enough task-scoped sessions → average of those (rounded to a configurable
 *   step);
 * - otherwise enough user-wide sessions → average of those;
 * - otherwise the configured baseline default.
 *
 * Out-of-range durations are treated as anomalous and excluded. Durations are
 * configuration, not biological claims; sparse data falls back to baseline.
 */
final class FocusBlockRecommender
{
    public const DEFAULT_MIN_MINUTES = 15;

    public const DEFAULT_MAX_MINUTES = 120;

    public const DEFAULT_BASELINE_MINUTES = 45;

    public const DEFAULT_ROUND_TO = 5;

    public const DEFAULT_MIN_SAMPLES = 3;

    public function __construct(
        private readonly int $minMinutes = self::DEFAULT_MIN_MINUTES,
        private readonly int $maxMinutes = self::DEFAULT_MAX_MINUTES,
        private readonly int $baselineMinutes = self::DEFAULT_BASELINE_MINUTES,
        private readonly int $roundTo = self::DEFAULT_ROUND_TO,
        private readonly int $minSamples = self::DEFAULT_MIN_SAMPLES,
    ) {
        if ($this->minMinutes > $this->maxMinutes) {
            throw new \InvalidArgumentException('Min focus minutes cannot exceed max.');
        }
        if ($this->roundTo < 1) {
            throw new \InvalidArgumentException('Round step must be positive.');
        }
    }

    /**
     * @param  array<int, FocusSession>  $taskSessions  task-scoped completed sessions
     * @param  array<int, FocusSession>  $userSessions  user-wide completed sessions
     */
    public function recommend(array $taskSessions, array $userSessions): FocusBlockRecommendation
    {
        if (count($taskSessions) >= $this->minSamples) {
            $avg = $this->averageOf($taskSessions);
            if ($avg !== null) {
                return new FocusBlockRecommendation(
                    $this->roundAndClamp($avg),
                    FocusBlockRecommendation::BASIS_TASK_PATTERNS,
                    count($taskSessions),
                    sprintf('Based on %d completed focus sessions on this task.', count($taskSessions)),
                );
            }
        }

        if (count($userSessions) >= $this->minSamples) {
            $avg = $this->averageOf($userSessions);
            if ($avg !== null) {
                return new FocusBlockRecommendation(
                    $this->roundAndClamp($avg),
                    FocusBlockRecommendation::BASIS_USER_PATTERNS,
                    count($userSessions),
                    sprintf('Based on %d recent completed focus sessions.', count($userSessions)),
                );
            }
        }

        return new FocusBlockRecommendation(
            $this->baselineMinutes,
            FocusBlockRecommendation::BASIS_BASELINE,
            0,
            'Not enough focus history; using the configured baseline duration.',
        );
    }

    /**
     * @param  array<int, FocusSession>  $sessions
     */
    private function averageOf(array $sessions): ?float
    {
        $durations = array_values(array_filter(
            array_map(static fn (FocusSession $session) => $session->durationMinutes, $sessions),
            fn (int $duration) => $duration >= $this->minMinutes && $duration <= $this->maxMinutes,
        ));

        if ($durations === []) {
            return null;
        }

        return array_sum($durations) / count($durations);
    }

    private function roundAndClamp(float $minutes): int
    {
        $rounded = (int) round($minutes / $this->roundTo) * $this->roundTo;

        return max($this->minMinutes, min($this->maxMinutes, $rounded));
    }
}
