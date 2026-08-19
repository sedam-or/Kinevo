<?php

namespace App\Application\Observability;

use App\Domain\Observability\ObservabilityService;

/**
 * SRS §16.5 minimum telemetry snapshot. Authenticated. Safe metadata only —
 * never sensitive content.
 */
final readonly class GetMetricsUseCase
{
    public function __construct(
        private ObservabilityService $service,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return $this->service->snapshot();
    }
}
