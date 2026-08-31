<?php

namespace Tests\Support;

use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ScheduleDraftRecord;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use Carbon\CarbonImmutable;

/**
 * In-memory double for the persisted-draft port (ADR-016 §2.5).
 */
final class FakeScheduleDraftStore implements ScheduleDraftRepository
{
    /** @var array<int, ScheduleDraftRecord> */
    public array $drafts = [];

    private int $nextDraftId = 1;

    public function findForUser(int $userId, int $draftId): ?ScheduleDraftRecord
    {
        $record = $this->drafts[$draftId] ?? null;

        return $record !== null && $record->userId === $userId ? $record : null;
    }

    public function listPendingForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->drafts,
            static fn (ScheduleDraftRecord $record) => $record->userId === $userId && $record->isPending(),
        ));
    }

    public function findPendingWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord
    {
        foreach ($this->drafts as $record) {
            if ($record->userId === $userId
                && $record->source === 'weekly'
                && $record->isPending()
                && $record->generatedForWeek?->toDateString() === $weekAnchor->toDateString()) {
                return $record;
            }
        }

        return null;
    }

    public function findWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord
    {
        foreach ($this->drafts as $record) {
            if ($record->userId === $userId
                && $record->source === 'weekly'
                && $record->generatedForWeek?->toDateString() === $weekAnchor->toDateString()) {
                return $record;
            }
        }

        return null;
    }

    public function listPendingWeeklyForUser(int $userId): array
    {
        return array_values(array_filter(
            $this->drafts,
            static fn (ScheduleDraftRecord $record) => $record->userId === $userId
                && $record->source === 'weekly'
                && $record->isPending(),
        ));
    }

    public function create(ScheduleDraftRecord $record): ScheduleDraftRecord
    {
        $id = $this->nextDraftId++;
        $stored = new ScheduleDraftRecord(
            $id,
            $record->userId,
            $record->source,
            $record->status,
            $record->payload,
            $record->baseVersion,
            $record->horizonFrom,
            $record->horizonTo,
            $record->generatedForWeek,
            CarbonImmutable::now(),
        );

        $this->drafts[$id] = $stored;

        return $stored;
    }

    public function updateStatus(int $userId, int $draftId, ScheduleDraftStatus $status): void
    {
        $record = $this->drafts[$draftId] ?? null;

        if ($record === null || $record->userId !== $userId) {
            return;
        }

        $this->drafts[$draftId] = new ScheduleDraftRecord(
            $record->id,
            $record->userId,
            $record->source,
            $status,
            $record->payload,
            $record->baseVersion,
            $record->horizonFrom,
            $record->horizonTo,
            $record->generatedForWeek,
            $record->createdAt,
        );
    }

    public function refreshWeekly(int $userId, int $draftId, array $payload, int $baseVersion, CarbonImmutable $horizonFrom, CarbonImmutable $horizonTo): ScheduleDraftRecord
    {
        $record = $this->drafts[$draftId];

        $refreshed = new ScheduleDraftRecord(
            $record->id,
            $record->userId,
            $record->source,
            $record->status,
            $payload,
            $baseVersion,
            $horizonFrom,
            $horizonTo,
            $record->generatedForWeek,
            $record->createdAt,
        );

        $this->drafts[$draftId] = $refreshed;

        return $refreshed;
    }
}
