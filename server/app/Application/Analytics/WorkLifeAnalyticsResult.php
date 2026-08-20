<?php

namespace App\Application\Analytics;

use App\Domain\Analytics\WorkLifeRatio;

/**
 * Work-Life Ratio analytics for a period (TASK-126/TASK-135): the aggregated
 * productive and Recharge minutes, the normative WorkRatio/RechargeRatio, a
 * per-day series, a period comparison against the preceding equal-length
 * period, a weekly trend, and descriptive exceptions. The ratio is a
 * time-balance indicator, never a health diagnosis.
 *
 * @phpstan-type WorkLifeDay array{date: string, productive_minutes: int, recharge_minutes: int, work_ratio: float, recharge_ratio: float, band: string}
 * @phpstan-type WorkLifeTrendWeek array{week_start: string, productive_minutes: int, recharge_minutes: int, work_ratio: float, recharge_ratio: float}
 * @phpstan-type WorkLifeException array{date: string, kind: string, description: string}
 * @phpstan-type PreviousPeriod array{from: string, to: string, productive_minutes: int, recharge_minutes: int, work_ratio: float, recharge_ratio: float}
 */
final readonly class WorkLifeAnalyticsResult
{
    /**
     * @param  array<int, WorkLifeDay>  $days
     * @param  array<int, WorkLifeTrendWeek>  $trend
     * @param  array<int, WorkLifeException>  $exceptions
     */
    public function __construct(
        public string $from,
        public string $to,
        public WorkLifeRatio $ratio,
        public array $days,
        public array $previous,
        public array $trend,
        public array $exceptions,
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
            'previous' => $this->previous,
            'trend' => $this->trend,
            'exceptions' => $this->exceptions,
            'disclaimer' => WorkLifeRatio::DISCLAIMER,
        ];
    }
}
