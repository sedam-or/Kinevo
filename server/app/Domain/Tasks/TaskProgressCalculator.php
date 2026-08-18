<?php

namespace App\Domain\Tasks;

/**
 * FR-09 derived progress: completed subtasks / total subtasks × 100.
 */
final readonly class TaskProgressCalculator
{
    /**
     * @param  array<int, Subtask>  $subtasks
     */
    public function calculate(array $subtasks): int
    {
        $total = count($subtasks);

        if ($total === 0) {
            return 0;
        }

        $completed = 0;
        foreach ($subtasks as $subtask) {
            if ($subtask->completed) {
                $completed++;
            }
        }

        return (int) round(($completed / $total) * 100);
    }
}
