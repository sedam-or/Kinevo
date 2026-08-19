<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\ScheduleOverride;
use InvalidArgumentException;

final readonly class GetScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
    ) {}

    public function __invoke(int $userId, int $overrideId): ScheduleOverride
    {
        $override = $this->overrides->findForUser($userId, $overrideId);

        if ($override === null) {
            throw new InvalidArgumentException('Schedule override not found.');
        }

        return $override;
    }
}
