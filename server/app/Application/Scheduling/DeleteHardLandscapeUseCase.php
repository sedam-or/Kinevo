<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;

final readonly class DeleteHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
    ) {}

    public function __invoke(int $userId, int $eventId): void
    {
        $this->events->deleteForUser($userId, $eventId);
    }
}
