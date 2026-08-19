<?php

namespace App\Domain\Focus\Contracts;

use App\Domain\Focus\FocusSession;
use Carbon\CarbonImmutable;

interface FocusSessionRepository
{
    public function create(FocusSession $session): FocusSession;

    /**
     * @return array<int, FocusSession>
     */
    public function listForUser(int $userId, ?int $taskId = null, int $limit = 50): array;

    /**
     * Completed sessions started at/after a cutoff (recommendation window).
     *
     * @return array<int, FocusSession>
     */
    public function listSince(int $userId, CarbonImmutable $since, int $limit = 200): array;
}
