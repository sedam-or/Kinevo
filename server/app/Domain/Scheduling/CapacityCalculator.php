<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\CapacityMinutes;

/**
 * Capacity feedback loop (FR-49, AC-09). Estimates Effective Capacity from 2–4
 * recent weeks and derives the recommended next-week productive load.
 *
 * Feedback rule (FR-49 Business Rules + AC-09):
 * - average realization < 80% → next-week load reduced proportionally;
 * - average realization > 90% and no burnout signal → Boost/backlog fill offered;
 * - Emergency/Break weeks are excluded so they cannot corrupt the estimate;
 * - no eligible weeks → baseline capacity at LOW confidence, no aggressive
 *   adjustment; fewer weeks than 2 use the available minimum (LOW confidence).
 */
final class CapacityCalculator
{
    public const LOW_THRESHOLD = 0.80;

    public const HIGH_THRESHOLD = 0.90;

    /**
     * @param  array<int, WeekCapacitySample>  $samples  recent weeks (2–4 expected)
     */
    public function estimate(array $samples, int $targetCapacityMinutes, bool $burnoutSignal = false): EffectiveCapacity
    {
        $eligible = array_values(array_filter(
            $samples,
            static fn (WeekCapacitySample $sample) => $sample->isEligible(),
        ));

        $eligibleCount = count($eligible);
        $confidence = $this->confidence($eligibleCount);

        if ($eligibleCount === 0) {
            return new EffectiveCapacity(
                new CapacityMinutes($targetCapacityMinutes),
                0.0,
                $confidence,
                'MAINTAIN',
                'No eligible history; using baseline capacity with no aggressive adjustment.',
            );
        }

        $ratios = array_map(
            static fn (WeekCapacitySample $sample) => $sample->realizationRatio(),
            $eligible,
        );
        $average = array_sum($ratios) / count($ratios);

        if ($average < self::LOW_THRESHOLD) {
            $reduced = (int) floor($targetCapacityMinutes * $average);

            return new EffectiveCapacity(
                new CapacityMinutes($reduced),
                $average,
                $confidence,
                'REDUCE_LOAD',
                sprintf(
                    'Average realization %.0f%% is below the 80%% target; next-week load reduced to %.0f%% of capacity.',
                    $average * 100,
                    $average * 100,
                ),
            );
        }

        if ($average > self::HIGH_THRESHOLD && ! $burnoutSignal) {
            return new EffectiveCapacity(
                new CapacityMinutes($targetCapacityMinutes),
                $average,
                $confidence,
                'BOOST_AVAILABLE',
                sprintf(
                    'Average realization %.0f%% exceeds 90%% with no burnout signal; Boost/backlog fill available.',
                    $average * 100,
                ),
            );
        }

        return new EffectiveCapacity(
            new CapacityMinutes($targetCapacityMinutes),
            $average,
            $confidence,
            'MAINTAIN',
            sprintf('Average realization %.0f%% is within the normal band.', $average * 100),
        );
    }

    private function confidence(int $eligibleWeeks): string
    {
        return $eligibleWeeks >= 4 ? 'HIGH' : ($eligibleWeeks >= 2 ? 'MEDIUM' : 'LOW');
    }
}
