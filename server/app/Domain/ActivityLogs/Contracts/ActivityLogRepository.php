<?php

namespace App\Domain\ActivityLogs\Contracts;

use App\Domain\ActivityLogs\ActivityLog;
use Carbon\CarbonImmutable;

interface ActivityLogRepository
{
    public function append(ActivityLog $log): ActivityLog;

    /**
     * @return array<int, ActivityLog>
     */
    public function listForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array;

    /**
     * @return array<int, ActivityLog>
     */
    public function exportForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array;
}
