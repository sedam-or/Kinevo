<?php

namespace App\Application\Analytics;

use App\Domain\Analytics\WorkLifeRatio;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use Carbon\CarbonImmutable;

/**
 * Work-Life Ratio analytics over a period (TASK-126): aggregates productive
 * (focus) minutes and Recharge minutes per day from the already-recorded
 * sessions and derives the normative WorkRatio/RechargeRatio. The analytics
 * layer consumes generated data only — no business calculations are duplicated
 * in controllers.
 */
final readonly class GetWorkLifeAnalyticsUseCase
{
    public function __construct(
        private FocusSessionRepository $focusSessions,
        private RechargeSessionRepository $recharges,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): WorkLifeAnalyticsResult
    {
        $days = [];
        $productiveTotal = 0;
        $rechargeTotal = 0;

        $cursor = $from->startOfDay();
        while ($cursor->lte($to->endOfDay())) {
            $dayStart = $cursor;
            $dayEnd = $cursor->endOfDay();

            $productive = $this->focusSessions->sumDurationMinutesBetween($userId, $dayStart, $dayEnd);
            $recharge = $this->recharges->sumCompletedMinutesBetween($userId, $dayStart, $dayEnd);

            $productiveTotal += $productive;
            $rechargeTotal += $recharge;

            $dayRatio = WorkLifeRatio::fromMinutes($productive, $recharge);
            $days[] = [
                'date' => $dayStart->toDateString(),
                'productive_minutes' => $productive,
                'recharge_minutes' => $recharge,
                'work_ratio' => $dayRatio->workRatio,
                'recharge_ratio' => $dayRatio->rechargeRatio,
                'band' => $dayRatio->band(),
            ];

            $cursor = $cursor->addDay();
        }

        return new WorkLifeAnalyticsResult(
            $from->toDateString(),
            $to->toDateString(),
            WorkLifeRatio::fromMinutes($productiveTotal, $rechargeTotal),
            $days,
        );
    }
}
