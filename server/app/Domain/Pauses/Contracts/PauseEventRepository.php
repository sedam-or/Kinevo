<?php

namespace App\Domain\Pauses\Contracts;

use App\Domain\Pauses\PauseEvent;
use Carbon\CarbonImmutable;

interface PauseEventRepository
{
    public function create(PauseEvent $event): PauseEvent;

    public function findForUser(int $userId, int $eventId): ?PauseEvent;

    /**
     * The emergency pause tagging the week containing the given date, or null.
     */
    public function findEmergencyForWeek(int $userId, CarbonImmutable $date): ?PauseEvent;

    /**
     * Whether an emergency pause tags the week containing the given date.
     * Used to suppress notifications during an exceptional week (FR-47).
     */
    public function isWeekExceptional(int $userId, CarbonImmutable $date): bool;

    /**
     * @return array<int, PauseEvent>
     */
    public function listForUser(int $userId, int $limit = 50): array;
}
