<?php

namespace App\Application\Scheduling;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Domain\ActivityLogs\ActivityLog;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\Resolution\EffectiveLandscapeResolver;
use App\Domain\Scheduling\ScheduleOverride;
use App\Domain\Scheduling\ScheduleOverrideConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleOverrideType;
use Carbon\CarbonImmutable;

final readonly class CreateScheduleOverrideUseCase
{
    public function __construct(
        private ScheduleOverrideRepository $overrides,
        private HardLandscapeRepository $hardLandscape,
        private EffectiveLandscapeResolver $landscapeResolver,
        private RecordActivityUseCase $recordActivity,
    ) {}

    public function __invoke(
        int $userId,
        int $hardLandscapeEventId,
        ScheduleOverrideType $type,
        CarbonImmutable $effectiveFrom,
        CarbonImmutable $effectiveTo,
        CarbonImmutable $overrideStartAt,
        CarbonImmutable $overrideEndAt,
        ?string $reason = null,
        bool $cancelsOccurrence = false,
    ): ScheduleOverride {
        $override = ScheduleOverride::create(
            $userId,
            $hardLandscapeEventId,
            $type,
            $effectiveFrom,
            $effectiveTo,
            $overrideStartAt,
            $overrideEndAt,
            $reason,
            $cancelsOccurrence,
        );

        // ADR-015 write-side validation: the shifted/excepted effective
        // occurrences must not overlap another source's effective
        // occurrences. Base Hard Landscape rows can never overlap each other
        // (enforced at Hard Landscape creation), so any cross-source overlap
        // here would be introduced by the override → 409.
        $this->assertNoEffectiveCrossSourceCollision($userId, $override);

        $created = $this->overrides->create($override);

        // The override is part of the effective landscape as soon as it is
        // created — record it as an auditable schedule mutation (ADR-015).
        $this->recordActivity->__invoke(ActivityLog::create(
            $userId,
            ActivityEventType::scheduleOverrideApplied(),
            'schedule_override',
            $created->id,
            'Schedule override applied',
            operationId: 'schedule_override_applied:'.$userId.':'.$created->id,
            payload: [
                'hard_landscape_event_id' => $created->hardLandscapeEventId,
                'type' => $created->type->value,
                'cancels_occurrence' => $created->cancelsOccurrence,
            ],
        ));

        return $created;
    }

    /**
     * Resolve the user's landscape WITH the proposed override included and
     * reject any overlap between occurrences of different source events.
     */
    private function assertNoEffectiveCrossSourceCollision(int $userId, ScheduleOverride $proposed): void
    {
        $sources = $this->hardLandscape->listForUser($userId);
        $existing = $this->overrides->listForUser($userId);
        $existing[] = $proposed->withId(PHP_INT_MAX); // participate in resolution deterministically

        $probeFrom = $proposed->effectiveFrom->startOfDay()->subDays(1);
        $probeTo = $proposed->overrideEndAt->endOfDay()->addDays(1);

        $resolution = $this->landscapeResolver->resolve($sources, $existing, $probeFrom, $probeTo);

        $bySource = [];
        foreach ($resolution->occurrences as $occurrence) {
            $bySource[$occurrence->sourceEventId][] = $occurrence;
        }

        $proposedOccurrences = $bySource[$proposed->hardLandscapeEventId] ?? [];

        foreach ($proposedOccurrences as $proposedOccurrence) {
            foreach ($bySource as $sourceId => $occurrences) {
                if ($sourceId === $proposed->hardLandscapeEventId) {
                    continue;
                }

                foreach ($occurrences as $other) {
                    if ($proposedOccurrence->timeRange()->overlaps($other->timeRange())) {
                        throw new ScheduleOverrideConflict;
                    }
                }
            }
        }
    }
}
