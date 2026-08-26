<?php

namespace App\Domain\Ai\Entities;

use Carbon\CarbonImmutable;

/**
 * TASK-P25-010 — AI usage/cost alert event (domain event first; channel
 * delivery comes later). user_id NULL = ops-side alert (logged + stored,
 * never exposed to end users). User alerts surface as unread in-app until
 * marked seen.
 */
final readonly class AiCostAlert
{
    public const KIND_USER_USAGE_THRESHOLD = 'user.usage_threshold';

    public const KIND_OPS_DAILY_COST = 'ops.daily_cost';

    public const KIND_OPS_USER_ANOMALY = 'ops.user_anomaly';

    public const KINDS = [
        self::KIND_USER_USAGE_THRESHOLD,
        self::KIND_OPS_DAILY_COST,
        self::KIND_OPS_USER_ANOMALY,
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $userId,
        public readonly string $kind,
        public readonly ?int $threshold,
        public readonly array $context,
        public readonly ?CarbonImmutable $seenAt,
        public readonly CarbonImmutable $createdAt,
    ) {}

    public static function userUsageThreshold(
        ?int $userId,
        int $threshold,
        array $context = [],
        ?CarbonImmutable $createdAt = null,
        ?int $id = null,
    ): self {
        return new self(
            $id,
            $userId,
            self::KIND_USER_USAGE_THRESHOLD,
            $threshold,
            $context,
            null,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public static function ops(
        string $kind,
        ?int $threshold,
        array $context = [],
        ?int $userId = null,
        ?CarbonImmutable $createdAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $kind,
            $threshold,
            $context,
            null,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self($id, $this->userId, $this->kind, $this->threshold, $this->context, $this->seenAt, $this->createdAt);
    }

    public function withSeen(CarbonImmutable $seenAt): self
    {
        return new self($this->id, $this->userId, $this->kind, $this->threshold, $this->context, $seenAt, $this->createdAt);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'threshold' => $this->threshold,
            'context' => $this->context,
            'seen_at' => $this->seenAt?->toISOString(),
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
