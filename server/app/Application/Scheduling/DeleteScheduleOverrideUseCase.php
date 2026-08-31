<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use InvalidArgumentException;

final readonly class DeleteScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
        private ScheduleImpactService $impact,
    ) {}

    public function __invoke(int $userId, int $overrideId): void
    {
        $existing = $this->overrides->findForUser($userId, $overrideId);

        if ($existing === null) {
            throw new InvalidArgumentException('Schedule override not found.');
        }

        $this->overrides->deleteForUser($userId, $overrideId);

        $this->impact->assess(
            $userId,
            $existing->effectiveFrom->min($existing->overrideStartAt),
            $existing->effectiveTo->max($existing->overrideEndAt),
            'schedule_override_deleted',
            [$overrideId],
        );
    }
}
