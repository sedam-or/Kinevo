<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\TimeRange;

/**
 * Human-readable explanation for a single automated placement/movement
 * (FR-63, scheduling-engine §Explainability contract).
 */
final class PlacementExplanation
{
    /**
     * @param  array<int, ExplanationReason>  $reasons
     * @param  array<int, string>  $acceptedConstraints
     * @param  array<int, string>  $rejectedAlternatives
     */
    public function __construct(
        public readonly string $taskId,
        public readonly string $title,
        public readonly TimeRange $slot,
        public readonly array $reasons,
        public readonly string $summary,
        public readonly array $acceptedConstraints,
        public readonly array $rejectedAlternatives,
        public readonly ?string $primaryPriority,
        public readonly ?string $deadlinePressure,
        public readonly ?string $capacityContext,
        public readonly ?string $softContextSignal,
    ) {}
}
