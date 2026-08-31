<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\ScheduleReviewState;

/**
 * Port for the per-user schedule review state (ADR-016 §2.3). One row per user.
 */
interface ScheduleReviewRepository
{
    public function findForUser(int $userId): ScheduleReviewState;

    public function markNeedsReview(int $userId, array $reasons, int $scheduleVersion): ScheduleReviewState;

    public function markReviewed(int $userId, int $scheduleVersion): ScheduleReviewState;
}
