<?php

namespace App\Application\Progress;

use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Progress\ProgressEvent;
use Carbon\CarbonImmutable;

/**
 * List meaningful progress events for the owner (SRS §6.8 informational feed).
 */
final readonly class ListProgressEventsUseCase
{
    public function __construct(
        private ProgressEventRepository $events,
    ) {}

    /**
     * @return array<int, ProgressEvent>
     */
    public function __invoke(
        int $userId,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        ?string $eventType = null,
        int $limit = 50,
    ): array {
        return $this->events->listForUser($userId, $from, $to, $eventType, $limit);
    }
}
