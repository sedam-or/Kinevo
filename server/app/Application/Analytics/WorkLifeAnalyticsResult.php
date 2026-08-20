<?php

namespace App\Application\Analytics;

use App\Domain\Analytics\WorkLifeRatio;

/**
 * Work-Life Ratio analytics for a period (TASK-126): the aggregated productive
 * and recharge minutes, the normative WorkRatio/RechargeRatio, a per-day series,
 * and the time-balance disclaimer.
 *
 * @phpstan-type WorkLifeDay array{date: string, productive_minutes: int, recharge_minutes: int, work_ratio: float, recharge_ratio: float, band: string}
 */
final readonly class WorkLifeAnalyticsResult
{
    /**
     * @param  array<int, WorkLifeDay>  $days
     */
    public function __construct(
        public string $from,
        public string $to,
        public WorkLifeRatio $ratio,
        public array $days,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'productive_minutes' => $this->ratio->productiveMinutes,
            'recharge_minutes' => $this->ratio->rechargeMinutes,
            'total_minutes' => $this->ratio->totalMinutes(),
            'work_ratio' => $this->ratio->workRatio,
            'recharge_ratio' => $this->ratio->rechargeRatio,
            'band' => $this->ratio->band(),
            'days' => $this->days,
            'disclaimer' => WorkLifeRatio::DISCLAIMER,
        ];
    }
}
