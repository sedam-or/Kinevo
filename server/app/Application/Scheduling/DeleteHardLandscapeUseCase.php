<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use InvalidArgumentException;

final readonly class DeleteHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
        private ScheduleImpactService $impact,
    ) {}

    public function __invoke(int $userId, int $eventId): void
    {
        $event = $this->events->findForUser($userId, $eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Hard Landscape event not found.');
        }

        $this->events->deleteForUser($userId, $eventId);

        // ADR-016 §2.3 — the vacated window may now leave flexible work.
        $this->impact->assess($userId, $event->startAt, $event->endAt, 'hard_landscape_deleted', [$eventId]);
    }
}
