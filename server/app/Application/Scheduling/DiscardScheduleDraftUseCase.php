<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use InvalidArgumentException;

/**
 * ADR-016 §2.5 — user discards a pending (weekly) draft. Cancel never
 * mutates accepted placements; the draft simply leaves the review queue.
 */
final readonly class DiscardScheduleDraftUseCase
{
    public function __construct(
        private ScheduleDraftRepository $drafts,
    ) {}

    public function __invoke(int $userId, int $draftId): void
    {
        $record = $this->drafts->findForUser($userId, $draftId);

        if ($record === null) {
            throw new InvalidArgumentException('Draft not found.');
        }

        if (! $record->isPending()) {
            throw new InvalidArgumentException('Draft is not pending.');
        }

        $this->drafts->updateStatus($userId, $draftId, ScheduleDraftStatus::discarded());
    }
}
