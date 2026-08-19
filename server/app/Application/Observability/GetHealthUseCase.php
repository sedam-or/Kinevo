<?php

namespace App\Application\Observability;

use App\Domain\Observability\ObservabilityService;

/**
 * Readiness health check (SRS §16.5, §16.3 "database health"). Public —
 * used by the reverse proxy / orchestrator. Reports DB health; sensitive
 * content is never included.
 */
final readonly class GetHealthUseCase
{
    public function __construct(
        private ObservabilityService $service,
    ) {}

    public function healthy(): bool
    {
        return $this->service->databaseHealthy();
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        return [
            'status' => $this->healthy() ? 'ok' : 'degraded',
            'database' => ['healthy' => $this->service->databaseHealthy()],
            'storage' => ['writable' => $this->service->storageWritable()],
            'checked_at' => now()->toISOString(),
        ];
    }
}
