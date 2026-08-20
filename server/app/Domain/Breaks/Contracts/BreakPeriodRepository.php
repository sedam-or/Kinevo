<?php

namespace App\Domain\Breaks\Contracts;

use App\Domain\Breaks\BreakPeriod;
use Carbon\CarbonImmutable;

interface BreakPeriodRepository
{
    public function create(BreakPeriod $period): BreakPeriod;

    public function findForUser(int $userId, int $periodId): ?BreakPeriod;

    /**
     * The user's currently active break period, or null. One active break at a
     * time (FR-36: a single confirmed Break Mode period).
     */
    public function findActiveForUser(int $userId): ?BreakPeriod;

    /**
     * Whether any active break period covers the given date.
     */
    public function coversDate(int $userId, CarbonImmutable $date): bool;

    /**
     * Whether any active break period covers any day of the week containing
     * the given date (FR-49: break weeks are tagged exceptional).
     */
    public function coversWeek(int $userId, CarbonImmutable $date): bool;

    /**
     * End the given active break period. Returns the ended period, or null when
     * the period does not belong to the user or is not active.
     */
    public function end(BreakPeriod $period): ?BreakPeriod;

    /**
     * @return array<int, BreakPeriod>
     */
    public function listForUser(int $userId, int $limit = 50): array;

    /**
     * Active break periods that end on or after the given date, ordered by end
     * date ascending. Used by the H-3 holiday-end notification scan (FR-39).
     *
     * @return array<int, BreakPeriod>
     */
    public function listActiveEndingOnOrAfter(int $userId, CarbonImmutable $date): array;
}
