<?php

namespace App\Domain\Boosts\Contracts;

use App\Domain\Boosts\BoostTarget;
use Carbon\CarbonImmutable;

interface BoostTargetRepository
{
    public function create(BoostTarget $target): BoostTarget;

    public function findForUser(int $userId, int $targetId): ?BoostTarget;

    /**
     * The user's currently active boost target, or null. One active boost target
     * at a time (saving a new target ends the previous one).
     */
    public function findActiveForUser(int $userId): ?BoostTarget;

    /**
     * The active boost target covering the given date, or null. Used by the
     * scheduler to resolve the effective target (FR-38).
     */
    public function findActiveOn(int $userId, CarbonImmutable $date): ?BoostTarget;

    /**
     * End the given active boost target. Returns the ended target, or null when
     * it does not belong to the user or is not active.
     */
    public function end(BoostTarget $target): ?BoostTarget;

    /**
     * @return array<int, BoostTarget>
     */
    public function listForUser(int $userId, int $limit = 50): array;
}
