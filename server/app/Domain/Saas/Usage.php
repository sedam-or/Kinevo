<?php

namespace App\Domain\Saas;

/**
 * TASK-P23-005 — usage consumption for a single entitlement key within a
 * billing period. Allowance lives in the Plan; this is the used side.
 */
final readonly class Usage
{
    public function __construct(
        public string $key,
        public string $period,
        public int $consumed,
    ) {}

    public function remaining(int $allowance): int
    {
        return max(0, $allowance - $this->consumed);
    }
}
