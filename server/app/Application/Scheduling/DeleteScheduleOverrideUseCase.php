<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;

final readonly class DeleteScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
    ) {}

    public function __invoke(int $userId, int $overrideId): void
    {
        $this->overrides->deleteForUser($userId, $overrideId);
    }
}
