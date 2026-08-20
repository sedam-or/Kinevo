<?php

namespace App\Application\Analytics\Results;

/**
 * Pillar realization read model (FR-12, TASK-133): for each of the four life
 * pillars plus Uncategorized, the completed task minutes realized in the period
 * versus the mapped program weekly targets. Percent is null (N/A) when there is
 * no target — never NaN.
 *
 * @phpstan-type PillarRow array{key: string, label: string, realization_minutes: int, target_minutes: int, percent: float|null}
 */
final readonly class PillarAnalytics
{
    /**
     * @param  array<int, PillarRow>  $pillars
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $pillars,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'pillars' => $this->pillars,
        ];
    }
}
