<?php

namespace App\Application\Adaptive;

use App\Domain\Adaptive\BurnoutSignal;
use App\Domain\Adaptive\BurnoutSignalDetector;
use App\Domain\Adaptive\Contracts\ContextObservationRepository;
use Carbon\CarbonImmutable;

/**
 * Evaluate the burnout warning over a recent context window (TASK-060). The
 * boolean feeds the Capacity feedback loop (FR-49) to suppress aggressive
 * boosts. Advisory heuristic, never a clinical assessment.
 */
final readonly class GetBurnoutSignalUseCase
{
    public const WINDOW_DAYS = 14;

    public function __construct(
        private ContextObservationRepository $observations,
        private BurnoutSignalDetector $detector,
    ) {}

    public function __invoke(int $userId): BurnoutSignal
    {
        $since = CarbonImmutable::now()->subDays(self::WINDOW_DAYS);

        return $this->detector->detect($this->observations->listSince($userId, $since));
    }
}
