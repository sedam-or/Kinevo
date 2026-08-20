<?php

namespace App\Application\Recharge;

use App\Domain\Focus\Contracts\FocusSessionRepository;
use App\Domain\Recharge\Contracts\RechargeSessionRepository;
use Carbon\CarbonImmutable;

/**
 * Current recharge context for the Today view (FR-05): the active recharge
 * session (if any), whether the Recharge timer CTA is due (the second completed
 * focus session of a pair in the relevant day context), and the day's
 * Work-Life Ratio derived from productive (focus) minutes and Recharge minutes.
 *
 * @return array{
 *     recharge: array<string, mixed>|null,
 *     cue_available: bool,
 *     completed_focus_today: int,
 *     due_recharges: int,
 *     completed_recharges_today: int,
 *     recharge_minutes_today: int,
 *     productive_minutes_today: int,
 *     work_ratio: float,
 *     recharge_ratio: float
 * }
 */
final readonly class GetRechargeStatusUseCase
{
    public function __construct(
        private RechargeSessionRepository $recharges,
        private FocusSessionRepository $focusSessions,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $now): array
    {
        $active = $this->recharges->findActiveForUser($userId);

        $completedFocusToday = $this->focusSessions->countCompletedBetween($userId, $start, $end);
        $dueRecharges = intdiv($completedFocusToday, 2);
        $completedRechargesToday = $this->recharges->countCompletedBetween($userId, $start, $end);

        $rechargeMinutes = $this->recharges->sumCompletedMinutesBetween($userId, $start, $end);
        $productiveMinutes = $this->focusSessions->sumDurationMinutesBetween($userId, $start, $end);

        $total = $productiveMinutes + $rechargeMinutes;
        $workRatio = $total > 0 ? round($productiveMinutes / $total, 4) : 0.0;
        $rechargeRatio = $total > 0 ? round($rechargeMinutes / $total, 4) : 0.0;

        return [
            'recharge' => $active?->toArray($now),
            'cue_available' => $active === null && $dueRecharges > $completedRechargesToday,
            'completed_focus_today' => $completedFocusToday,
            'due_recharges' => $dueRecharges,
            'completed_recharges_today' => $completedRechargesToday,
            'recharge_minutes_today' => $rechargeMinutes,
            'productive_minutes_today' => $productiveMinutes,
            'work_ratio' => $workRatio,
            'recharge_ratio' => $rechargeRatio,
        ];
    }
}
