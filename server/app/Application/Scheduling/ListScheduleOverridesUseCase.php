<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\ScheduleOverride;

final readonly class ListScheduleOverridesUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
    ) {}

    /**
     * @return array<int, ScheduleOverride>
     */
    public function __invoke(int $userId): array
    {
        return $this->overrides->listForUser($userId);
    }
}
