<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;

final readonly class ListHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
    ) {}

    /**
     * @return array<int, HardLandscapeEvent>
     */
    public function __invoke(int $userId): array
    {
        return $this->events->listForUser($userId);
    }
}
