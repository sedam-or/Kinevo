<?php

namespace App\Application\ActivityLogs;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;

/**
 * Appends an immutable activity record (FR-34). Duplicate operation IDs are
 * ignored (idempotent), correction is by compensating event.
 */
final readonly class RecordActivityUseCase
{
    public function __construct(
        private ActivityLogRepository $logs,
    ) {}

    public function __invoke(ActivityLog $log): ActivityLog
    {
        return $this->logs->append($log);
    }
}
