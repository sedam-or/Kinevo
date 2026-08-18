<?php

namespace App\Application\ActivityLogs;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use Carbon\CarbonImmutable;

/**
 * Inspects a user's activity log (FR-34: detailed inspection).
 */
final readonly class ListActivityLogsUseCase
{
    public function __construct(
        private ActivityLogRepository $logs,
    ) {}

    /**
     * @return array<int, ActivityLog>
     */
    public function __invoke(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array {
        return $this->logs->listForUser($userId, $from, $to, $eventType, $limit);
    }
}
