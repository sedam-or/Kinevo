<?php

namespace App\Domain\Ai\Entities;

use App\Domain\Ai\ValueObjects\AiProposalType;
use Carbon\CarbonImmutable;

/**
 * A validated structured proposal awaiting a user decision (SRS §7.7, FR-62).
 * Persisted only after structured validation passed (FR-61). `decision` is
 * pending|accepted|rejected|edited; `operationId` records the resulting domain
 * operation once applied.
 */
final class AiProposal
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly AiProposalType $type,
        public readonly int $schemaVersion,
        public readonly array $payload,
        public readonly string $decision,
        public readonly ?string $operationId,
        public readonly ?CarbonImmutable $createdAt,
    ) {}

    public static function pending(
        int $userId,
        AiProposalType $type,
        int $schemaVersion,
        array $payload,
        ?CarbonImmutable $createdAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $type,
            $schemaVersion,
            $payload,
            'pending',
            null,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->type,
            $this->schemaVersion,
            $this->payload,
            $this->decision,
            $this->operationId,
            $this->createdAt,
        );
    }

    public function withDecision(string $decision, ?string $operationId = null): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->type,
            $this->schemaVersion,
            $this->payload,
            $decision,
            $operationId ?? $this->operationId,
            $this->createdAt,
        );
    }

    public function withPayload(array $payload, string $decision = 'edited'): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->type,
            $this->schemaVersion,
            $payload,
            $decision,
            $this->operationId,
            $this->createdAt,
        );
    }

    public function isPending(): bool
    {
        return $this->decision === 'pending';
    }

    /** A user-edited proposal keeps its approval gate: it may still be accepted. */
    public function isApplicable(): bool
    {
        return in_array($this->decision, ['pending', 'edited'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'proposal_type' => $this->type->value,
            'schema_version' => $this->schemaVersion,
            'payload' => $this->payload,
            'decision' => $this->decision,
            'operation_id' => $this->operationId,
            'created_at' => $this->createdAt?->toISOString(),
        ];
    }
}
