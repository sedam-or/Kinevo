<?php

namespace Tests\Support;

use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use Carbon\CarbonImmutable;

/**
 * In-memory activity-log fake for pure unit tests. Duplicate operation IDs
 * are ignored, mirroring the Eloquent repository's idempotency contract.
 */
final class FakeActivityLogRepository implements ActivityLogRepository
{
    /** @var list<ActivityLog> */
    public array $logs = [];

    public function append(ActivityLog $log): ActivityLog
    {
        if ($log->operationId !== null) {
            foreach ($this->logs as $existing) {
                if ($existing->operationId === $log->operationId && $existing->userId === $log->userId) {
                    return $existing;
                }
            }
        }

        $id = count($this->logs) + 1;
        $this->logs[] = $log->withId($id);

        return $this->logs[$id - 1];
    }

    public function listForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array {
        return array_slice(
            array_values(array_filter(
                $this->logs,
                static fn (ActivityLog $log) => $log->userId === $userId
                    && ($eventType === null || $log->eventType->value === $eventType),
            )),
            0,
            $limit,
        );
    }

    public function exportForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): array {
        return array_values(array_filter(
            $this->logs,
            static fn (ActivityLog $log) => $log->userId === $userId,
        ));
    }
}
