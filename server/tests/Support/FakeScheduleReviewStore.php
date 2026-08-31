<?php

namespace Tests\Support;

use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\ScheduleReviewState;
use Carbon\CarbonImmutable;

/**
 * In-memory double for the ADR-016 schedule review state port.
 */
final class FakeScheduleReviewStore implements ScheduleReviewRepository
{
    /** @var array<int, ScheduleReviewState> */
    public array $reviewStates = [];

    public function findForUser(int $userId): ScheduleReviewState
    {
        return $this->reviewStates[$userId] ?? new ScheduleReviewState($userId);
    }

    public function markNeedsReview(int $userId, array $reasons, int $scheduleVersion): ScheduleReviewState
    {
        $state = $this->findForUser($userId)->withNeedsReview($reasons, CarbonImmutable::now(), $scheduleVersion);
        $this->reviewStates[$userId] = $state;

        return $state;
    }

    public function markReviewed(int $userId, int $scheduleVersion): ScheduleReviewState
    {
        $state = $this->findForUser($userId)->reviewed($scheduleVersion);
        $this->reviewStates[$userId] = $state;

        return $state;
    }
}
