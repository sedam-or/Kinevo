<?php

namespace App\Domain\ActivityLogs;

use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use Carbon\CarbonImmutable;

/**
 * Immutable activity record (FR-34). Append-only: correction happens by
 * compensating event, never by destructive edit.
 */
final class ActivityLog
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ActivityEventType $eventType,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly ?string $title,
        public readonly CarbonImmutable $eventAt,
        public readonly ?string $operationId,
        public readonly array $payload,
    ) {}

    public static function create(
        int $userId,
        ActivityEventType $eventType,
        string $entityType,
        int $entityId,
        ?string $title = null,
        ?CarbonImmutable $eventAt = null,
        ?string $operationId = null,
        array $payload = [],
    ): self {
        return new self(
            null,
            $userId,
            $eventType,
            $entityType,
            $entityId,
            $title,
            $eventAt ?? CarbonImmutable::now(),
            $operationId,
            $payload,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->eventType,
            $this->entityType,
            $this->entityId,
            $this->title,
            $this->eventAt,
            $this->operationId,
            $this->payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'event_type' => $this->eventType->value,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'title' => $this->title,
            'event_at' => $this->eventAt->toISOString(),
            'operation_id' => $this->operationId,
            'payload' => $this->payload,
        ];
    }
}
