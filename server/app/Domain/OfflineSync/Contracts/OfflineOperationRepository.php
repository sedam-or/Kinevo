<?php

namespace App\Domain\OfflineSync\Contracts;

use App\Domain\OfflineSync\OfflineOperationRecord;
use Carbon\CarbonImmutable;

/**
 * ADR-017 §2.4 — operation ledger port. The unique (user_id, operation_id)
 * constraint is the at-most-once guard.
 */
interface OfflineOperationRepository
{
    public function find(int $userId, string $operationId): ?OfflineOperationRecord;

    /**
     * Record an applied/conflict/rejected outcome. The caller owns the
     * transaction (per-operation atomicity, §2.6).
     */
    public function record(
        int $userId,
        string $operationId,
        string $operationType,
        string $entityType,
        ?int $entityId,
        string $payloadHash,
        string $status,
        ?array $result,
    ): OfflineOperationRecord;

    /**
     * Delete ledger rows older than the retention horizon (ADR-017 §2.5).
     * Returns the number of pruned rows.
     */
    public function pruneOlderThan(CarbonImmutable $before): int;
}
