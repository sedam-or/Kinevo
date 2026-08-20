<?php

namespace App\Application\Imports;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use Carbon\CarbonImmutable;

/**
 * Computes conflict flags for staged iCal rows against existing Hard Landscape
 * and against each other (intra-import). A row that overlaps an existing event
 * — or an earlier non-conflicting row — is flagged and is never persisted on
 * confirm (FR-30/TASK-142: do not automatically overwrite existing Hard
 * Landscape). Deterministic: rows are evaluated in staged order and the first
 * non-conflicting row in a collision wins.
 */
final readonly class IcalConflictResolver
{
    public function __construct(
        private HardLandscapeRepository $hardLandscape,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function resolve(int $userId, array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $minStart = null;
        $maxEnd = null;
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse($row['start_at']);
            $end = CarbonImmutable::parse($row['end_at']);
            $minStart = $minStart === null || $start->lt($minStart) ? $start : $minStart;
            $maxEnd = $maxEnd === null || $end->gt($maxEnd) ? $end : $maxEnd;
        }

        $blocks = [];
        foreach ($this->hardLandscape->listForUserInRange($userId, $minStart, $maxEnd) as $event) {
            $blocks[] = ['start' => $event->startAt, 'end' => $event->endAt, 'title' => $event->title];
        }

        foreach ($rows as $key => $row) {
            $start = CarbonImmutable::parse($row['start_at']);
            $end = CarbonImmutable::parse($row['end_at']);

            $hit = null;
            foreach ($blocks as $block) {
                if ($block['start']->lt($end) && $block['end']->gt($start)) {
                    $hit = $block['title'];
                    break;
                }
            }

            if ($hit !== null) {
                $rows[$key]['conflict'] = true;
                $rows[$key]['conflict_with'] = $hit;
            } else {
                $rows[$key]['conflict'] = false;
                $rows[$key]['conflict_with'] = null;
                $blocks[] = ['start' => $start, 'end' => $end, 'title' => (string) ($row['summary'] ?? 'Imported event')];
            }
        }

        return $rows;
    }
}
