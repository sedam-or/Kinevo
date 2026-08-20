<?php

namespace App\Domain\Analytics;

/**
 * Work-Life Ratio (SRS FR-05 Postconditions: the Work-Life Ratio includes the
 * recorded Recharge duration; FR-05 Business Rules: Recharge is Recharge, never
 * Productive Time). Normative formula: workRatio = productive / (productive +
 * recharge); rechargeRatio = recharge / (productive + recharge).
 *
 * The ratios describe how the user's tracked time is distributed between focus
 * and recharge. This is a time-balance indicator only — never a health
 * diagnosis.
 */
final readonly class WorkLifeRatio
{
    public const DISCLAIMER = 'Time-balance indicator. Not a health diagnosis.';

    public function __construct(
        public int $productiveMinutes,
        public int $rechargeMinutes,
        public float $workRatio,
        public float $rechargeRatio,
    ) {}

    public static function fromMinutes(int $productiveMinutes, int $rechargeMinutes): self
    {
        $total = $productiveMinutes + $rechargeMinutes;

        return new self(
            $productiveMinutes,
            $rechargeMinutes,
            $total > 0 ? round($productiveMinutes / $total, 4) : 0.0,
            $total > 0 ? round($rechargeMinutes / $total, 4) : 0.0,
        );
    }

    public function totalMinutes(): int
    {
        return $this->productiveMinutes + $this->rechargeMinutes;
    }

    /**
     * Descriptive band for the time distribution. Purely descriptive of the
     * recorded mix — it is not a health assessment.
     */
    public function band(): string
    {
        if ($this->totalMinutes() === 0) {
            return 'no_data';
        }

        if ($this->workRatio >= 0.80) {
            return 'work_leaning';
        }

        if ($this->rechargeRatio > 0.45) {
            return 'recharge_leaning';
        }

        return 'balanced';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'productive_minutes' => $this->productiveMinutes,
            'recharge_minutes' => $this->rechargeMinutes,
            'total_minutes' => $this->totalMinutes(),
            'work_ratio' => $this->workRatio,
            'recharge_ratio' => $this->rechargeRatio,
            'band' => $this->band(),
            'disclaimer' => self::DISCLAIMER,
        ];
    }
}
