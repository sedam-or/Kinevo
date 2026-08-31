<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * ADR-016 §2.5 — a persisted planning draft (currently weekly-sourced only).
 * The payload is the exact draft JSON the client echoes to the existing apply
 * endpoint; base_version carries the conflict semantics (stale apply → 409).
 */
final class ScheduleDraftRecord
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $source,
        public readonly ScheduleDraftStatus $status,
        public readonly array $payload,
        public readonly int $baseVersion,
        public readonly CarbonImmutable $horizonFrom,
        public readonly CarbonImmutable $horizonTo,
        public readonly ?CarbonImmutable $generatedForWeek,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {
        if ($this->baseVersion < 1) {
            throw new InvalidArgumentException('Draft base version must be a positive integer.');
        }
    }

    public static function weekly(
        int $userId,
        array $payload,
        int $baseVersion,
        CarbonImmutable $horizonFrom,
        CarbonImmutable $horizonTo,
        CarbonImmutable $generatedForWeek,
    ): self {
        return new self(
            null,
            $userId,
            'weekly',
            ScheduleDraftStatus::pending(),
            $payload,
            $baseVersion,
            $horizonFrom,
            $horizonTo,
            $generatedForWeek,
        );
    }

    public function isPending(): bool
    {
        return $this->status->equals(ScheduleDraftStatus::pending());
    }

    /**
     * ADR-016 §2.5 — staleness is derived: the base version the draft was
     * computed against no longer matches the live schedule.
     */
    public function isStale(int $currentScheduleVersion): bool
    {
        return $this->isPending() && $currentScheduleVersion !== $this->baseVersion;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(int $currentScheduleVersion): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'status' => $this->status->value,
            'stale' => $this->isStale($currentScheduleVersion),
            'payload' => $this->payload,
            'base_version' => $this->baseVersion,
            'horizon_from' => $this->horizonFrom->toDateString(),
            'horizon_to' => $this->horizonTo->toDateString(),
            'generated_for_week' => $this->generatedForWeek?->toDateString(),
            'created_at' => $this->createdAt?->toISOString(),
        ];
    }
}
