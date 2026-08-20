<?php

namespace App\Application\Boosts;

use App\Domain\Boosts\BoostTarget;
use App\Domain\Boosts\Contracts\BoostTargetRepository;
use Carbon\CarbonImmutable;

/**
 * Resolve the effective boost target for a date (FR-38: detect mode → load
 * effective target). Returns null when no active boost target covers the date,
 * which means the scheduler falls back to the normal target (FR-38 Exception
 * Flow / Alternative Flow).
 */
final readonly class GetEffectiveTargetUseCase
{
    public function __construct(
        private BoostTargetRepository $boostTargets,
    ) {}

    public function __invoke(int $userId, CarbonImmutable $date): ?BoostTarget
    {
        return $this->boostTargets->findActiveOn($userId, $date);
    }
}
