<?php

namespace App\Domain\Progress\Contracts;

use App\Domain\Progress\ProgressEvent;
use Carbon\CarbonImmutable;

interface ProgressEventRepository
{
    public function append(ProgressEvent $event): ProgressEvent;

    /**
     * @return array<int, ProgressEvent>
     */
    public function listForUser(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array;
}
