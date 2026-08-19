<?php

namespace App\Application\Adaptive;

use App\Domain\Adaptive\ContextFitService;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;
use Carbon\CarbonImmutable;

/**
 * Build the soft context-fit map for a set of task ids over a recent window
 * (FR-59). Consumed by the schedule assembly path to populate the context_fit
 * soft signal; sparse data falls back to the neutral baseline.
 */
final readonly class BuildContextFitMapUseCase
{
    public const WINDOW_DAYS = 14;

    public function __construct(
        private ContextObservationRepository $observations,
        private ContextFitService $service,
    ) {}

    /**
     * @param  array<int, int>  $taskIds
     * @return array<string, float> taskId → context fit 0..1
     */
    public function __invoke(int $userId, array $taskIds, ?int $windowDays = null): array
    {
        $since = CarbonImmutable::now()->subDays($windowDays ?? self::WINDOW_DAYS);

        return $this->service->fitMap(
            $this->observations->listSince($userId, $since),
            $taskIds,
        );
    }
}
