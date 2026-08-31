<?php

namespace App\Domain\OfflineSync;

/**
 * ADR-017 §2.6 — result of executing one offline operation against the
 * canonical use cases. `result` carries the full canonical entity shape for
 * the immediate response; the LEDGER stores only {entityId, version} (§2.20).
 */
final class OperationApplyResult
{
    /**
     * @param  array<string, mixed>  $result  canonical response body (e.g. ['task' => [...]] )
     */
    public function __construct(
        public readonly array $result,
        public readonly ?int $entityId,
        public readonly ?int $version,
    ) {}
}
