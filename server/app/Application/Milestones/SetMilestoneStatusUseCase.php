<?php

namespace App\Application\Milestones;

use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use App\Domain\Milestones\ValueObjects\MilestoneStatus;
use Carbon\CarbonImmutable;

/**
 * Applies an explicit state transition to a milestone
 * (FR-51 create/block/complete/drop lifecycle).
 */
final readonly class SetMilestoneStatusUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
    ) {}

    public function __invoke(
        int $userId,
        int $milestoneId,
        MilestoneStatus $status,
        ?CarbonImmutable $now = null,
    ): Milestone {
        $milestone = (new GetMilestoneUseCase($this->milestones))($userId, $milestoneId);

        $updated = $milestone->withStatus($status, $now);

        return $this->milestones->update($updated);
    }
}
