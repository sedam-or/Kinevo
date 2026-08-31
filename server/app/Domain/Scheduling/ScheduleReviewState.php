<?php

namespace App\Domain\Scheduling;

use Carbon\CarbonImmutable;

/**
 * ADR-016 §2.3 — per-user schedule review state. Reality mutations mark the
 * accepted schedule as impacted (needs review); explicit apply or a no-change
 * Sync Now clears it.
 */
final class ScheduleReviewState
{
    /**
     * @param  array<string, mixed>|null  $reasons
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $needsReview = false,
        public readonly ?array $reasons = null,
        public readonly ?CarbonImmutable $impactedAt = null,
        public readonly int $lastReviewedVersion = 1,
    ) {}

    public function withNeedsReview(array $reasons, CarbonImmutable $impactedAt, int $scheduleVersion): self
    {
        return new self($this->userId, true, $reasons, $impactedAt, $this->lastReviewedVersion);
    }

    public function reviewed(int $scheduleVersion): self
    {
        return new self($this->userId, false, null, null, max($scheduleVersion, $this->lastReviewedVersion));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'needs_review' => $this->needsReview,
            'reasons' => $this->reasons,
            'impacted_at' => $this->impactedAt?->toISOString(),
            'last_reviewed_version' => $this->lastReviewedVersion,
        ];
    }
}
