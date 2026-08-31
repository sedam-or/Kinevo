<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\TaskMove;
use App\Domain\Scheduling\ValueObjects\ScheduleVersion;

/**
 * ADR-016 §2.2 — outcome of a manual Sync Now computation. The schedule is
 * never mutated by a sync; the diff is applied only through the existing
 * explicit reschedule-apply flow (409 on stale base).
 */
final class SyncNowResult
{
    public const NO_CHANGES = 'no_changes';

    public const PROPOSAL = 'proposal';

    public const RUN_IN_PROGRESS = 'run_in_progress';

    /**
     * @param  array<int, TaskMove>  $moves
     * @param  array<int, string>  $conflictTaskIds
     */
    public function __construct(
        public readonly string $status,
        public readonly ScheduleVersion $baseVersion,
        public readonly bool $needsReview,
        public readonly array $moves = [],
        public readonly array $conflictTaskIds = [],
        public readonly ?int $newVersion = null,
    ) {}

    public static function runInProgress(bool $needsReview): self
    {
        return new self(self::RUN_IN_PROGRESS, new ScheduleVersion(1), $needsReview);
    }
}
