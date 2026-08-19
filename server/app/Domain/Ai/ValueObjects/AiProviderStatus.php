<?php

namespace App\Domain\Ai\ValueObjects;

/**
 * Provider health/telemetry snapshot (SRS §17.8 "AI provider status"). Safe
 * metadata only; no private content.
 */
final readonly class AiProviderStatus
{
    public function __construct(
        public string $provider,
        public string $model,
        public bool $available,
        public ?int $latencyMs = null,
        public ?string $error = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'available' => $this->available,
            'latency_ms' => $this->latencyMs,
            'error' => $this->error,
        ];
    }
}
