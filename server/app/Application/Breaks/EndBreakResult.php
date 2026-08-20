<?php

namespace App\Application\Breaks;

/**
 * Result of ending a Break Mode period (FR-36/FR-39). Carries the summary used
 * by the H-3 holiday-end notification and the in-app end summary.
 */
final class EndBreakResult
{
    public function __construct(
        public readonly bool $applied,
        public readonly ?int $breakPeriodId,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?int $durationDays,
        public readonly string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied,
            'break_period_id' => $this->breakPeriodId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'duration_days' => $this->durationDays,
            'explanation' => $this->explanation,
        ];
    }
}
