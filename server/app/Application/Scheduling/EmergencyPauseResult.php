<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleVersion;

/**
 * Result of an Emergency Pause (FR-07): which week was tagged as exceptional,
 * which tasks were kept in place, which tasks moved +1 week, which tasks could
 * not be placed (conflict), and a human-readable explanation.
 */
final readonly class EmergencyPauseResult
{
    /**
     * @param  array<int, string>  $keepTaskIds
     * @param  array<int, array{task_id: string, title: string, from: array<string, string>|null, to: array<string, string>}>  $moves
     * @param  array<int, string>  $conflictTaskIds
     */
    public function __construct(
        public ScheduleVersion $version,
        public bool $applied,
        public string $weekStart,
        public string $weekEnd,
        public array $keepTaskIds,
        public array $moves,
        public array $conflictTaskIds,
        public string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version->value,
            'applied' => $this->applied,
            'week_start' => $this->weekStart,
            'week_end' => $this->weekEnd,
            'keep_task_ids' => $this->keepTaskIds,
            'moves' => $this->moves,
            'conflict_task_ids' => $this->conflictTaskIds,
            'explanation' => $this->explanation,
        ];
    }
}
