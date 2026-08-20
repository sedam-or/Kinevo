<?php

namespace App\Application\Analytics\Results;

/**
 * Focus read model (TASK-130): productive focus-session volume per day over the
 * period (completed sessions and total minutes).
 *
 * @phpstan-type FocusDay array{date: string, sessions: int, minutes: int}
 */
final readonly class FocusAnalytics
{
    /**
     * @param  array<int, FocusDay>  $days
     */
    public function __construct(
        public string $from,
        public string $to,
        public int $totalSessions,
        public int $totalMinutes,
        public array $days,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'total_sessions' => $this->totalSessions,
            'total_minutes' => $this->totalMinutes,
            'days' => $this->days,
        ];
    }
}
