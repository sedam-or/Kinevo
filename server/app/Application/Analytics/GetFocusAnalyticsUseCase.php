<?php

namespace App\Application\Analytics;

use App\Application\Analytics\Results\FocusAnalytics;
use App\Domain\Focus\Contracts\FocusSessionRepository;
use Carbon\CarbonImmutable;

/**
 * Focus read model (TASK-130): productive focus-session volume per day over the
 * period (completed sessions and total minutes).
 */
final readonly class GetFocusAnalyticsUseCase
{
    public function __construct(
        private FocusSessionRepository $focusSessions,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $from, CarbonImmutable $to): FocusAnalytics
    {
        $days = [];
        $totalSessions = 0;
        $totalMinutes = 0;

        $cursor = $from->startOfDay();
        while ($cursor->lte($to->endOfDay())) {
            $dayStart = $cursor;
            $dayEnd = $cursor->endOfDay();

            $sessions = $this->focusSessions->countCompletedBetween($userId, $dayStart, $dayEnd);
            $minutes = $this->focusSessions->sumDurationMinutesBetween($userId, $dayStart, $dayEnd);

            $totalSessions += $sessions;
            $totalMinutes += $minutes;

            $days[] = [
                'date' => $dayStart->toDateString(),
                'sessions' => $sessions,
                'minutes' => $minutes,
            ];

            $cursor = $cursor->addDay();
        }

        return new FocusAnalytics(
            $from->toDateString(),
            $to->toDateString(),
            $totalSessions,
            $totalMinutes,
            $days,
        );
    }
}
