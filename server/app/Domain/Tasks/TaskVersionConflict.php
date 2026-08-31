<?php

namespace App\Domain\Tasks;

use RuntimeException;

/**
 * ADR-017 §2.11 — optimistic concurrency guard for task updates. An offline
 * (or online) `base_version` that no longer matches the current task version
 * is a deterministic conflict — never a silent overwrite.
 */
final class TaskVersionConflict extends RuntimeException
{
    public function __construct(int $expectedVersion, int $actualVersion)
    {
        parent::__construct(
            "Task version conflict: expected {$expectedVersion}, got {$actualVersion}."
        );
    }
}
