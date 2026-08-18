<?php

namespace App\Domain\Scheduling;

use Carbon\CarbonImmutable;

/**
 * Derives stable reason codes (FR-63 example list) from a task and its soft
 * ranking signals. Deterministic and independently testable.
 */
final class ReasonMapper
{
    private const CONTEXT_FIT_HIGH = 0.7;

    private const PROGRESS_HIGH = 70;

    /**
     * @return array<int, ExplanationReason>
     */
    public function reasons(ScheduleTask $task, RankingCandidate $candidate): array
    {
        $reasons = [];

        if ($task->isLocked) {
            $reasons[] = new ExplanationReason(ExplanationReason::LOCK_PROTECTED);
        }

        if ($task->isSacredAnchor) {
            $reasons[] = new ExplanationReason(ExplanationReason::SACRED_ANCHOR);
        }

        if ($this->deadlineNear($task, $candidate)) {
            $reasons[] = new ExplanationReason(ExplanationReason::DEADLINE_PRIORITY);
        }

        if ($candidate->contextFit !== null && $candidate->contextFit >= self::CONTEXT_FIT_HIGH) {
            $reasons[] = new ExplanationReason(ExplanationReason::ENERGY_FIT);
        }

        if ($candidate->progress >= self::PROGRESS_HIGH) {
            $reasons[] = new ExplanationReason(ExplanationReason::PROGRESS_VALUE);
        }

        if ($candidate->fragmentationPenalty > 0.0) {
            $reasons[] = new ExplanationReason(ExplanationReason::CONTEXT_SWITCH_PENALTY);
        }

        if ($candidate->continuityPreference) {
            $reasons[] = new ExplanationReason(ExplanationReason::CONTINUITY_PREFERENCE);
        }

        return $reasons;
    }

    private function deadlineNear(ScheduleTask $task, RankingCandidate $candidate): bool
    {
        $deadline = $task->taskDeadline
            ?? $task->milestoneDeadline
            ?? $task->goalDeadline;

        if ($deadline === null) {
            return false;
        }

        $reference = $candidate->slot->start ?? CarbonImmutable::now();

        return $deadline->lessThanOrEqualTo($reference->addDays(3));
    }
}
