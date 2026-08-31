<?php

namespace App\Domain\OfflineSync;

use InvalidArgumentException;

/**
 * ADR-017 §2.3 — the same operation_id was replayed with a DIFFERENT payload.
 * Deterministic rejection; the at-most-once ledger is never overwritten.
 */
final class OfflineOperationReused extends InvalidArgumentException
{
    public function __construct(string $operationId)
    {
        parent::__construct("operation_id {$operationId} was already used with a different payload.");
    }
}
