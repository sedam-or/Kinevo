<?php

namespace App\Domain\Progress;

use App\Domain\Progress\ValueObjects\ProgressEventType;
use Carbon\CarbonImmutable;

/**
 * Append-only meaningful progress event (SRS §6.8, §12.5). A progress event
 * MUST reference the domain change that created it (via operation_id) and is an
 * informational input to analytics/adaptive recommendations — it never
 * overwrites historical activity logs.
 */
final class ProgressEvent
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly ProgressEventType $eventType,
        public readonly string $entityType,
        public readonly int $entityId,
        public readonly ?string $title,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?string $operationId,
        public readonly array $payload,
    ) {}

    public static function create(
        int $userId,
        ProgressEventType $eventType,
        string $entityType,
        int $entityId,
        ?string $title = null,
        ?CarbonImmutable $occurredAt = null,
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
            $occurredAt ?? CarbonImmutable::now(),
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
            $this->occurredAt,
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
            'occurred_at' => $this->occurredAt->toISOString(),
            'operation_id' => $this->operationId,
            'payload' => $this->payload,
        ];
    }
}
