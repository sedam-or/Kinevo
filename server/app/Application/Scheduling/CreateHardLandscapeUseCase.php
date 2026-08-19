<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use Carbon\CarbonImmutable;

final readonly class CreateHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        HardLandscapeType $type,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        ?string $recurrence = null,
    ): HardLandscapeEvent {
        $event = HardLandscapeEvent::create(
            $userId,
            $title,
            $type,
            $startAt,
            $endAt,
            $recurrence,
        );

        return $this->events->create($event);
    }
}
