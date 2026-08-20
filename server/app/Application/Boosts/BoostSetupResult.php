<?php

namespace App\Application\Boosts;

use App\Domain\Boosts\BoostTarget;

/**
 * Boost Mode setup view (FR-37 Normal Flow: show current targets → compute
 * recommendations). Available when Break Mode is confirmed; the recommendation
 * is suppressed while a burnout signal is active (FR-49).
 */
final readonly class BoostSetupResult
{
    public function __construct(
        public bool $eligible,
        public ?BoostTarget $activeTarget,
        public BoostRecommendation $recommendation,
        public int $safetyCapPercent,
        public ?int $breakPeriodId,
        public ?string $breakStartDate,
        public ?string $breakEndDate,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'active_target' => $this->activeTarget?->toArray(),
            'recommendation' => [
                'eligible' => $this->recommendation->eligible,
                'recommendation' => $this->recommendation->recommendation,
                'reason' => $this->recommendation->reason,
                'average_realization' => $this->recommendation->averageRealization,
                'recommended_target_percent' => $this->recommendation->recommendedTargetPercent,
            ],
            'safety_cap_percent' => $this->safetyCapPercent,
            'break_period_id' => $this->breakPeriodId,
            'break_start_date' => $this->breakStartDate,
            'break_end_date' => $this->breakEndDate,
        ];
    }
}
