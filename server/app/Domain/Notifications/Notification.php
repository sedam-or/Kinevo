<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\ValueObjects\NotificationType;
use Carbon\CarbonImmutable;

/**
 * In-app notification record (SRS §7 notifications table, FR-35/FR-47).
 * Immutable value semantics: state changes return a new instance.
 */
final class Notification
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly NotificationType $type,
        public readonly ?CarbonImmutable $scheduledFor,
        public readonly ?string $title,
        public readonly array $payload,
        public readonly ?CarbonImmutable $readAt,
    ) {}

    public static function create(
        int $userId,
        NotificationType $type,
        ?CarbonImmutable $scheduledFor = null,
        ?string $title = null,
        array $payload = [],
        ?CarbonImmutable $readAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $type,
            $scheduledFor,
            $title,
            $payload,
            $readAt,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->type,
            $this->scheduledFor,
            $this->title,
            $this->payload,
            $this->readAt,
        );
    }

    public function markRead(?CarbonImmutable $at = null): self
    {
        if ($this->readAt !== null) {
            return $this;
        }

        return new self(
            $this->id,
            $this->userId,
            $this->type,
            $this->scheduledFor,
            $this->title,
            $this->payload,
            $at ?? CarbonImmutable::now(),
        );
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'type' => $this->type->value,
            'scheduled_for' => $this->scheduledFor?->toDateString(),
            'title' => $this->title,
            'payload' => $this->payload,
            'read_at' => $this->readAt?->toISOString(),
        ];
    }
}
