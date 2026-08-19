<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class UpdateScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
    ) {}

    public function __invoke(
        int $userId,
        int $overrideId,
        ?int $hardLandscapeEventId,
        ?ScheduleOverrideType $type,
        ?CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
        ?CarbonImmutable $overrideStartAt,
        ?CarbonImmutable $overrideEndAt,
        ?string $reason,
    ): ScheduleOverride {
        $existing = $this->overrides->findForUser($userId, $overrideId);

        if ($existing === null) {
            throw new InvalidArgumentException('Schedule override not found.');
        }

        $updated = new ScheduleOverride(
            $existing->id,
            $userId,
            $hardLandscapeEventId ?? $existing->hardLandscapeEventId,
            $type ?? $existing->type,
            $effectiveFrom ?? $existing->effectiveFrom,
            $effectiveTo ?? $existing->effectiveTo,
            $overrideStartAt ?? $existing->overrideStartAt,
            $overrideEndAt ?? $existing->overrideEndAt,
            $reason !== null ? $reason : $existing->reason,
            $existing->createdAt,
            $existing->updatedAt,
        );

        return $this->overrides->update($updated);
    }
}
