<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\HardLandscapeEvent;
use Carbon\CarbonImmutable;

interface HardLandscapeRepository
{
    public function findForUser(int $userId, int $eventId): ?HardLandscapeEvent;

    /**
     * @return array<int, HardLandscapeEvent>
     */
    public function listForUser(int $userId): array;

    /**
     * Events that overlap the given day (for the Today/schedule query views).
     *
     * @return array<int, HardLandscapeEvent>
     */
    public function listForUserOnDate(int $userId, CarbonImmutable $date): array;

    /**
     * @return array<int, HardLandscapeEvent>
     */
    public function listForUserInRange(int $userId, CarbonImmutable $from, CarbonImmutable $to): array;

    public function create(HardLandscapeEvent $event): HardLandscapeEvent;

    public function update(HardLandscapeEvent $event): HardLandscapeEvent;

    public function deleteForUser(int $userId, int $eventId): void;
}
