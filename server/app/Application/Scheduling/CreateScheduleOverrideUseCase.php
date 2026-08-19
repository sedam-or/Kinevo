<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use Carbon\CarbonImmutable;

final readonly class CreateScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
    ) {}

    public function __invoke(
        int $userId,
        int $hardLandscapeEventId,
        ScheduleOverrideType $type,
        CarbonImmutable $effectiveFrom,
        CarbonImmutable $effectiveTo,
        CarbonImmutable $overrideStartAt,
        CarbonImmutable $overrideEndAt,
        ?string $reason = null,
    ): ScheduleOverride {
        $override = ScheduleOverride::create(
            $userId,
            $hardLandscapeEventId,
            $type,
            $effectiveFrom,
            $effectiveTo,
            $overrideStartAt,
            $overrideEndAt,
            $reason,
        );

        return $this->overrides->create($override);
    }
}
