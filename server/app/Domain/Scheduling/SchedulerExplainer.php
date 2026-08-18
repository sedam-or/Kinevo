<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Builds human-readable scheduler explanations (FR-63). Maps the observable
 * soft component scores plus draft context into stable reason codes and a
 * readable summary. Deterministic: same inputs → same explanation.
 */
final class SchedulerExplainer
{
    /**
     * @param  array<int, ExplanationReason>  $reasons
     */
    public function explain(
        DraftAssignment $assignment,
        RankingCandidate $candidate,
        array $reasons,
        string $constraintViolationSummary,
    ): PlacementExplanation {
        $summary = $this->buildSummary($assignment, $candidate, $reasons, $constraintViolationSummary);

        return new PlacementExplanation(
            taskId: $assignment->taskId,
            title: $assignment->title,
            slot: $assignment->slot,
            reasons: $reasons,
            summary: $summary,
            acceptedConstraints: $this->acceptedConstraints($candidate, $constraintViolationSummary),
            rejectedAlternatives: $this->rejectedAlternatives($constraintViolationSummary),
            primaryPriority: $this->primaryPriority($candidate),
            deadlinePressure: $this->deadlinePressure($candidate),
            capacityContext: $this->capacityContext($candidate, $assignment->slot),
            softContextSignal: $this->softContextSignal($candidate),
        );
    }

    /**
     * @param  array<int, ExplanationReason>  $reasons
     */
    private function buildSummary(
        DraftAssignment $assignment,
        RankingCandidate $candidate,
        array $reasons,
        string $constraintViolationSummary,
    ): string {
        $reasonLabels = array_map(
            static fn (ExplanationReason $reason) => $reason->label(),
            $reasons,
        );

        if ($reasonLabels === []) {
            return "Placed \"{$assignment->title}\" at {$assignment->slot->start->toDateTimeString()}.";
        }

        return sprintf(
            'Placed "%s" at %s because %s.',
            $assignment->title,
            $assignment->slot->start->toDateTimeString(),
            lcfirst(implode('; ', $reasonLabels)),
        );
    }

    /**
     * @return array<int, string>
     */
    private function acceptedConstraints(RankingCandidate $candidate, string $constraintViolationSummary): array
    {
        $constraints = ['HARD_LANDSCAPE_COLLISION', 'LOCKED_TASK_MOVE', 'TEMPORAL_VALIDITY', 'ILLEGAL_OVERLAP'];

        if ($candidate->taskDeadline !== null) {
            $constraints[] = 'DEADLINE_FEASIBILITY';
        }
        if ($candidate->slot !== null) {
            $constraints[] = 'DURATION_FIT';
        }

        return $constraints;
    }

    /**
     * @return array<int, string>
     */
    private function rejectedAlternatives(string $constraintViolationSummary): array
    {
        if ($constraintViolationSummary === '') {
            return [];
        }

        return [$constraintViolationSummary];
    }

    private function primaryPriority(RankingCandidate $candidate): ?string
    {
        if ($candidate->slot === null) {
            return null;
        }

        return "tier-{$candidate->priorityTier->value}";
    }

    private function deadlinePressure(RankingCandidate $candidate): ?string
    {
        if ($candidate->taskDeadline === null) {
            return null;
        }

        $daysLeft = $candidate->taskDeadline->startOfDay()->diffInDays(
            $candidate->slot?->start->startOfDay() ?? $candidate->taskDeadline,
        );

        if ($daysLeft <= 0) {
            return 'overdue';
        }
        if ($daysLeft <= 1) {
            return 'high';
        }
        if ($daysLeft <= 3) {
            return 'medium';

        }

        return 'low';
    }

    private function capacityContext(RankingCandidate $candidate, TimeRange $slot): ?string
    {
        if ($candidate->estimatedMinutes === null) {
            return null;
        }

        $slotMinutes = $slot->durationMinutes()->value();

        return "slot {$slotMinutes} min for a {$candidate->estimatedMinutes} min task";
    }

    private function softContextSignal(RankingCandidate $candidate): ?string
    {
        if ($candidate->contextFit === null) {
            return null;
        }

        return number_format($candidate->contextFit, 2);
    }
}
