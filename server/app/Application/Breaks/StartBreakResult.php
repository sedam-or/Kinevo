<?php

namespace App\Application\Breaks;

/**
 * Result of confirming a Break Mode period (FR-36).
 */
final class StartBreakResult
{
    public function __construct(
        public readonly int $breakPeriodId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'break_period_id' => $this->breakPeriodId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'explanation' => $this->explanation,
        ];
    }
}
