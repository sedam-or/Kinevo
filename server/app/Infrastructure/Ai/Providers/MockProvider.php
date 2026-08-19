<?php

namespace App\Infrastructure\Ai\Providers;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;

/**
 * Deterministic provider for local development and testing. Canned output only;
 * never used as a runtime product dependency.
 */
final readonly class MockProvider implements AiProvider
{
    public function __construct(
        private string $model,
    ) {}

    public function name(): string
    {
        return 'mock';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function generate(AiRequest $request): AiResponse
    {
        return new AiResponse(
            "Mock AI response [{$request->role->value}]: {$request->prompt}",
            $this->name(),
            $this->model(),
            0,
            0,
            0,
        );
    }

    public function status(): AiProviderStatus
    {
        return new AiProviderStatus($this->name(), $this->model(), true, 0);
    }
}
