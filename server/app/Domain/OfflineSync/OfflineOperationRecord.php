<?php

namespace App\Domain\OfflineSync;

use Carbon\CarbonImmutable;

/**
 * A recorded ledger row (ADR-017 §2.4).
 */
final class OfflineOperationRecord
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $operationId,
        public readonly string $operationType,
        public readonly string $entityType,
        public readonly ?int $entityId,
        public readonly string $payloadHash,
        public readonly string $status,
        public readonly ?array $result,
        public readonly ?CarbonImmutable $processedAt,
        public readonly CarbonImmutable $createdAt,
    ) {}
}
