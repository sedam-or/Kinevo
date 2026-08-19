<?php

namespace App\Domain\Adaptive;

/**
 * Result of burnout signal detection (TASK-060 adaptive context, consumed by
 * the Capacity feedback loop FR-49). A heuristic warning signal only — not a
 * clinical or neurological assessment (FR-58 Business Rule).
 */
final readonly class BurnoutSignal
{
    public function __construct(
        public bool $active,
        public string $reason,
        public int $sampleCount,
    ) {}
}
