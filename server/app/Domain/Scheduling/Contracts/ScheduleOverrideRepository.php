<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\ScheduleOverride;

interface ScheduleOverrideRepository
{
    public function findForUser(int $userId, int $overrideId): ?ScheduleOverride;

    /**
     * @return array<int, ScheduleOverride>
     */
    public function listForUser(int $userId): array;

    /**
     * Overrides targeting a specific recurring source series.
     *
     * @return array<int, ScheduleOverride>
     */
    public function listForSource(int $userId, int $hardLandscapeEventId): array;

    public function create(ScheduleOverride $override): ScheduleOverride;

    public function update(ScheduleOverride $override): ScheduleOverride;

    public function deleteForUser(int $userId, int $overrideId): void;
}
