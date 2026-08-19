<?php

namespace App\Application\Progress;

use App\Domain\Progress\Contracts\ProgressEventRepository;
use App\Domain\Progress\ProgressEvent;

/**
 * Appends an append-only meaningful progress event (SRS §6.8). Duplicate
 * operation IDs are ignored (idempotent) — the operation id is the reference
 * to the domain change that created the event (§12.5).
 */
final readonly class RecordProgressEventUseCase
{
    public function __construct(
        private ProgressEventRepository $events,
    ) {}

    public function __invoke(ProgressEvent $event): ProgressEvent
    {
        return $this->events->append($event);
    }
}
