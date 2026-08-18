<?php

namespace App\Domain\Scheduling;

/**
 * A task that could not be placed in the draft, with a deterministic reason.
 */
final class UnassignedTask
{
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly string $reason,
    ) {}
}
