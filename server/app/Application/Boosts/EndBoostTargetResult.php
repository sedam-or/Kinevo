<?php

namespace App\Application\Boosts;

/**
 * Result of ending a Boost target early (FR-37/FR-38 Alternative Flow). The
 * scheduler returns to the baseline target once the boost ends.
 */
final readonly class EndBoostTargetResult
{
    public function __construct(
        public bool $applied,
        public ?int $targetId,
        public ?int $targetPercent,
        public ?string $startDate,
        public ?string $endDate,
        public string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'applied' => $this->applied,
            'target_id' => $this->targetId,
            'target_percent' => $this->targetPercent,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'explanation' => $this->explanation,
        ];
    }
}
