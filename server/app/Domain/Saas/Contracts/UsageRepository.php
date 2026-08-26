<?php

namespace App\Domain\Saas\Contracts;

use App\Domain\Saas\Usage;

interface UsageRepository
{
    public function forPeriod(int $userId, string $key, string $period): Usage;

    public function increment(int $userId, string $key, string $period, int $by = 1): Usage;
}
