<?php

namespace App\Infrastructure\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleReviewRepository;
use App\Domain\Scheduling\ScheduleReviewState;
use App\Models\ScheduleStateModel;
use Carbon\CarbonImmutable;

final readonly class EloquentScheduleReviewRepository implements ScheduleReviewRepository
{
    public function findForUser(int $userId): ScheduleReviewState
    {
        $model = ScheduleStateModel::query()->find($userId);

        if ($model === null) {
            return new ScheduleReviewState($userId);
        }

        return new ScheduleReviewState(
            $model->user_id,
            $model->needs_review,
            $model->reasons,
            $model->impacted_at !== null ? CarbonImmutable::instance($model->impacted_at) : null,
            (int) ($model->last_reviewed_version ?? 1),
        );
    }

    public function markNeedsReview(int $userId, array $reasons, int $scheduleVersion): ScheduleReviewState
    {
        $model = ScheduleStateModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'needs_review' => true,
                'reasons' => $reasons,
                'impacted_at' => CarbonImmutable::now(),
            ],
        );

        return new ScheduleReviewState(
            $model->user_id,
            true,
            $model->reasons,
            CarbonImmutable::instance($model->impacted_at),
            (int) ($model->last_reviewed_version ?? 1),
        );
    }

    public function markReviewed(int $userId, int $scheduleVersion): ScheduleReviewState
    {
        $model = ScheduleStateModel::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'needs_review' => false,
                'reasons' => null,
                'impacted_at' => null,
                'last_reviewed_version' => $scheduleVersion,
            ],
        );

        return new ScheduleReviewState($model->user_id, false, null, null, (int) ($model->last_reviewed_version ?? 1));
    }
}
