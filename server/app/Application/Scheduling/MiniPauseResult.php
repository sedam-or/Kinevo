<?php

namespace App\Application\Scheduling;

use App\Domain\Scheduling\ValueObjects\ScheduleVersion;

/**
 * Result of a Mini Pause (FR-07). Every eligible (unlocked, non-terminal) task
 * scheduled on the given date is moved to the next day's first feasible slot
 * that fits its duration. Locked tasks and tasks with no feasible next-day slot
 * stay in place; the latter are reported as visible conflicts. The change is
 * persisted atomically at the next schedule version and explained to the user.
 */
final readonly class MiniPauseResult
{
    /**
     * @param  array<int, array{
     *     task_id: string,
     *     title: string,
     *     from: array{start: string, end: string}|null,
     *     to: array{start: string, end: string},
     * }>  $moves
     * @param  array<int, string>  $conflictTaskIds
     */
    public function __construct(
        public readonly ScheduleVersion $version,
        public readonly bool $applied,
        public readonly array $moves,
        public readonly array $conflictTaskIds,
        public readonly string $explanation,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version->value,
            'applied' => $this->applied,
            'moves' => $this->moves,
            'conflict_task_ids' => array_values($this->conflictTaskIds),
            'explanation' => $this->explanation,
        ];
    }
}
