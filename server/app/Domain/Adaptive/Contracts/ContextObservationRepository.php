<?php

namespace App\Domain\Adaptive\Contracts;

use App\Domain\Adaptive\ContextObservation;
use Carbon\CarbonImmutable;

interface ContextObservationRepository
{
    public function create(ContextObservation $observation): ContextObservation;

    /**
     * Recent check-ins for a user, newest first.
     *
     * @return array<int, ContextObservation>
     */
    public function listForUser(int $userId, int $limit = 50): array;

    /**
     * Check-ins for a specific owned task, newest first.
     *
     * @return array<int, ContextObservation>
     */
    public function listForTask(int $userId, int $taskId, int $limit = 50): array;

    /**
     * Check-ins recorded at/after a cutoff, newest first (burnout window).
     *
     * @return array<int, ContextObservation>
     */
    public function listSince(int $userId, CarbonImmutable $since, int $limit = 200): array;
}
