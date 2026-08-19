<?php

namespace App\Application\Milestones;

use App\Application\Progress\RecordProgressEventUseCase;
use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use App\Domain\Progress\ProgressEventService;
use Carbon\CarbonImmutable;

/**
 * Applies an explicit state transition to a milestone
 * (FR-51 create/block/complete/drop lifecycle).
 * Advancing/completing appends meaningful progress events (SRS §6.8/§12.5).
 */
final readonly class SetMilestoneStatusUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
        private RecordProgressEventUseCase $recordProgressEvent,
        private ProgressEventService $progressEvents,
    ) {}

    public function __invoke(
        int $userId,
        int $milestoneId,
        MilestoneStatus $status,
        ?CarbonImmutable $now = null,
    ): Milestone {
        $milestone = (new GetMilestoneUseCase($this->milestones))($userId, $milestoneId);

        $updated = $milestone->withStatus($status, $now);

        $saved = $this->milestones->update($updated);

        if ($saved->isCompleted()) {
            $this->recordProgressEvent->__invoke($this->progressEvents->milestoneCompleted(
                $userId,
                $saved->id,
                $saved->title,
            ));
        } elseif ($status->equals(MilestoneStatus::active()) && ! $milestone->status->equals(MilestoneStatus::active())) {
            $this->recordProgressEvent->__invoke($this->progressEvents->milestoneAdvanced(
                $userId,
                $saved->id,
                $saved->title,
            ));
        }

        return $saved;
    }
}
