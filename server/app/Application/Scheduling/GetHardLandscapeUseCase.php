<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use InvalidArgumentException;

final readonly class GetHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
    ) {}

    public function __invoke(int $userId, int $eventId): HardLandscapeEvent
    {
        $event = $this->events->findForUser($userId, $eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Hard Landscape event not found.');
        }

        return $event;
    }
}
