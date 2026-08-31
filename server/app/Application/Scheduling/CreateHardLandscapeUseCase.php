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
        private ScheduleImpactService $impact,
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

        $created = $this->events->create($event);

        // ADR-016 §2.3 — post-commit, failure-isolated impact detection.
        $this->impact->assess($userId, $startAt, $endAt, 'hard_landscape_created', [$created->id]);

        return $created;
    }
}
