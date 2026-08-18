<?php

namespace App\Domain\Scheduling\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * Task/commitment deadline (domain-model recommended value object).
 */
final class Deadline
{
    public function __construct(
        public readonly CarbonImmutable $at,
    ) {}

    public function equals(self $other): bool
    {
        return $this->at->equalTo($other->at);
    }
}
