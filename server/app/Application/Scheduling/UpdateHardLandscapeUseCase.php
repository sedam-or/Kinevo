<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class UpdateHardLandscapeUseCase
{
    public function __construct(
        private HardLandscapeRepository $events,
        private ScheduleImpactService $impact,
    ) {}

    public function __invoke(
        int $userId,
        int $eventId,
        ?string $title,
        ?HardLandscapeType $type,
        ?CarbonImmutable $startAt,
        ?CarbonImmutable $endAt,
        ?string $recurrence,
    ): HardLandscapeEvent {
        $event = $this->events->findForUser($userId, $eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Hard Landscape event not found.');
        }

        $updated = new HardLandscapeEvent(
            $event->id,
            $userId,
            $title ?? $event->title,
            $type ?? $event->type,
            $startAt ?? $event->startAt,
            $endAt ?? $event->endAt,
            $recurrence !== null ? $recurrence : $event->recurrence,
            $event->createdAt,
            $event->updatedAt,
        );

        $saved = $this->events->update($updated);

        // ADR-016 §2.3 — assess the union of the old and new windows.
        $this->impact->assess(
            $userId,
            $event->startAt->min($updated->startAt),
            $event->endAt->max($updated->endAt),
            'hard_landscape_updated',
            [$saved->id],
        );

        return $saved;
    }
}
