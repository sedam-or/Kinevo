<?php

namespace App\Domain\Ai\Entities;

use Carbon\CarbonImmutable;

/**
 * AI run audit record (SRS §7.7). Safe metadata only — never prompts or note
 * content; the context hash is non-reversible and used only for matching.
 */
final class AiRun
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $provider,
        public readonly string $model,
        public readonly string $proposalType,
        public readonly ?int $schemaVersion,
        public readonly ?string $promptTemplateVersion,
        public readonly ?string $contextHash,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
        public readonly string $status,
        public readonly int $latencyMs,
        public readonly ?string $errorCode,
        public readonly CarbonImmutable $createdAt,
    ) {}

    public static function success(
        int $userId,
        string $provider,
        string $model,
        string $proposalType,
        ?int $schemaVersion,
        ?string $promptTemplateVersion,
        ?string $contextHash,
        ?int $inputTokens,
        ?int $outputTokens,
        int $latencyMs,
        ?CarbonImmutable $createdAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $provider,
            $model,
            $proposalType,
            $schemaVersion,
            $promptTemplateVersion,
            $contextHash,
            $inputTokens,
            $outputTokens,
            'success',
            $latencyMs,
            null,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public static function failed(
        int $userId,
        string $provider,
        string $model,
        string $proposalType,
        ?int $schemaVersion,
        ?string $contextHash,
        int $latencyMs,
        string $errorCode,
        ?CarbonImmutable $createdAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $provider,
            $model,
            $proposalType,
            $schemaVersion,
            null,
            $contextHash,
            null,
            null,
            'failed',
            $latencyMs,
            $errorCode,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->provider,
            $this->model,
            $this->proposalType,
            $this->schemaVersion,
            $this->promptTemplateVersion,
            $this->contextHash,
            $this->inputTokens,
            $this->outputTokens,
            $this->status,
            $this->latencyMs,
            $this->errorCode,
            $this->createdAt,
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
            'provider' => $this->provider,
            'model' => $this->model,
            'proposal_type' => $this->proposalType,
            'schema_version' => $this->schemaVersion,
            'prompt_template_version' => $this->promptTemplateVersion,
            'context_hash' => $this->contextHash,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'status' => $this->status,
            'latency_ms' => $this->latencyMs,
            'error_code' => $this->errorCode,
            'created_at' => $this->createdAt->toISOString(),
        ];
    }
}
