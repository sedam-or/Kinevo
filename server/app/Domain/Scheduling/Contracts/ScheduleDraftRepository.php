<?php

namespace App\Domain\Scheduling\Contracts;

use App\Domain\Scheduling\ScheduleDraftRecord;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use Carbon\CarbonImmutable;

/**
 * Port for persisted (weekly) planning drafts (ADR-016 §2.1, §2.5).
 */
interface ScheduleDraftRepository
{
    public function findForUser(int $userId, int $draftId): ?ScheduleDraftRecord;

    /**
     * @return array<int, ScheduleDraftRecord>
     */
    public function listPendingForUser(int $userId): array;

    public function findPendingWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord;

    /**
     * Any weekly draft for the week anchor, regardless of status — the weekly
     * dedup key (ADR-016 §2.1): applied/discarded weeks are not regenerated.
     */
    public function findWeeklyForWeek(int $userId, CarbonImmutable $weekAnchor): ?ScheduleDraftRecord;

    /**
     * Refresh a stale pending weekly draft in place (same week anchor — the
     * unique index allows exactly one row per user/week).
     */
    public function refreshWeekly(int $userId, int $draftId, array $payload, int $baseVersion, CarbonImmutable $horizonFrom, CarbonImmutable $horizonTo): ScheduleDraftRecord;

    /**
     * @return array<int, ScheduleDraftRecord>
     */
    public function listPendingWeeklyForUser(int $userId): array;

    public function create(ScheduleDraftRecord $record): ScheduleDraftRecord;

    public function updateStatus(int $userId, int $draftId, ScheduleDraftStatus $status): void;
}
