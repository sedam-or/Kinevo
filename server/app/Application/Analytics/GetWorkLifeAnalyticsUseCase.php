<?php

namespace App\Application\Analytics;

use App\Domain\Analytics\WorkLifeRatio;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use Carbon\CarbonImmutable;

/**
 * Work-Life Ratio analytics over a period (TASK-126/TASK-135): aggregates
 * productive (focus) minutes and Recharge minutes per day from the
 * already-recorded sessions and derives the normative WorkRatio/RechargeRatio,
 * a period comparison against the preceding equal-length period, a weekly
 * trend, and descriptive exceptions. The analytics layer consumes generated
 * data only — no business calculations are duplicated in controllers, and the
 * ratio is never presented as a medical/biological optimum.
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
        $trend = [];
        $productiveTotal = 0;
        $rechargeTotal = 0;

        $cursor = $from->startOfDay();
        $weekStart = $from->startOfWeek();
        $weekProductive = 0;
        $weekRecharge = 0;
        $exceptions = [];

        while ($cursor->lte($to->endOfDay())) {
            $dayStart = $cursor;
            $dayEnd = $cursor->endOfDay();

            $productive = $this->focusSessions->sumDurationMinutesBetween($userId, $dayStart, $dayEnd);
            $recharge = $this->recharges->sumCompletedMinutesBetween($userId, $dayStart, $dayEnd);

            $productiveTotal += $productive;
            $rechargeTotal += $recharge;
            $weekProductive += $productive;
            $weekRecharge += $recharge;

            $dayRatio = WorkLifeRatio::fromMinutes($productive, $recharge);
            $days[] = [
                'date' => $dayStart->toDateString(),
                'productive_minutes' => $productive,
                'recharge_minutes' => $recharge,
                'work_ratio' => $dayRatio->workRatio,
                'recharge_ratio' => $dayRatio->rechargeRatio,
                'band' => $dayRatio->band(),
            ];

            $this->collectException($exceptions, $dayStart, $productive, $recharge);

            // Close out each ISO week.
            if ($cursor->dayOfWeek === CarbonImmutable::SUNDAY || $cursor->toDateString() === $to->toDateString()) {
                $weekRatio = WorkLifeRatio::fromMinutes($weekProductive, $weekRecharge);
                $trend[] = [
                    'week_start' => $weekStart->toDateString(),
                    'productive_minutes' => $weekProductive,
                    'recharge_minutes' => $weekRecharge,
                    'work_ratio' => $weekRatio->workRatio,
                    'recharge_ratio' => $weekRatio->rechargeRatio,
                ];
                $weekStart = $cursor->addDay()->startOfWeek();
                $weekProductive = 0;
                $weekRecharge = 0;
            }

            $cursor = $cursor->addDay();
        }

        $previous = $this->previousPeriod($userId, $from, $to);

        return new WorkLifeAnalyticsResult(
            $from->toDateString(),
            $to->toDateString(),
            WorkLifeRatio::fromMinutes($productiveTotal, $rechargeTotal),
            $days,
            $previous,
            $trend,
            $exceptions,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $exceptions
     */
    private function collectException(array &$exceptions, CarbonImmutable $date, int $productive, int $recharge): void
    {
        if ($productive === 0 && $recharge === 0) {
            $exceptions[] = [
                'date' => $date->toDateString(),
                'kind' => 'no_data',
                'description' => 'No tracked focus or recharge time.',
            ];

            return;
        }

        if ($productive > 0 && $recharge === 0) {
            $exceptions[] = [
                'date' => $date->toDateString(),
                'kind' => 'work_only',
                'description' => 'Tracked focus time with no recharge time.',
            ];

            return;
        }

        if ($productive === 0 && $recharge > 0) {
            $exceptions[] = [
                'date' => $date->toDateString(),
                'kind' => 'recharge_only',
                'description' => 'Tracked recharge time with no focus time.',
            ];
        }
    }

    /**
     * The immediately preceding equal-length period for comparison.
     *
     * @return array{from: string, to: string, productive_minutes: int, recharge_minutes: int, work_ratio: float, recharge_ratio: float}
     */
    private function previousPeriod(int $userId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $lengthDays = (int) $from->startOfDay()->diffInDays($to->endOfDay()) + 1;
        $prevTo = $from->startOfDay()->subDay();
        $prevFrom = $prevTo->subDays($lengthDays - 1);

        $productive = $this->focusSessions->sumDurationMinutesBetween($userId, $prevFrom, $prevTo->endOfDay());
        $recharge = $this->recharges->sumCompletedMinutesBetween($userId, $prevFrom, $prevTo->endOfDay());
        $ratio = WorkLifeRatio::fromMinutes($productive, $recharge);

        return [
            'from' => $prevFrom->toDateString(),
            'to' => $prevTo->toDateString(),
            'productive_minutes' => $productive,
            'recharge_minutes' => $recharge,
            'work_ratio' => $ratio->workRatio,
            'recharge_ratio' => $ratio->rechargeRatio,
        ];
    }
}
