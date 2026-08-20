<?php

namespace App\Application\Boosts;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Boosts\Contracts\BoostTargetRepository;

/**
 * End the active Boost target early (FR-37/FR-38 Alternative Flow). When no
 * active target exists the request is a no-op. Ending the target returns the
 * scheduler to the baseline target.
 */
final readonly class EndBoostTargetUseCase
{
    public function __construct(
        private BoostTargetRepository $boostTargets,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(int $userId): EndBoostTargetResult
    {
        $active = $this->boostTargets->findActiveForUser($userId);

        if ($active === null) {
            return new EndBoostTargetResult(
                applied: false,
                targetId: null,
                targetPercent: null,
                startDate: null,
                endDate: null,
                explanation: 'No active boost target to end.',
            );
        }

        $this->boostTargets->end($active);

        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::boostEnd(),
            'boost_target',
            $active->id,
            'Boost target ended',
            payload: [
                'target_percent' => $active->targetPercent,
                'end_date' => $active->endDate->toDateString(),
            ],
        ));

        return new EndBoostTargetResult(
            applied: true,
            targetId: $active->id,
            targetPercent: $active->targetPercent,
            startDate: $active->startDate->toDateString(),
            endDate: $active->endDate->toDateString(),
            explanation: sprintf(
                'Boost target ended; scheduling returns to the baseline target.',
            ),
        );
    }
}
