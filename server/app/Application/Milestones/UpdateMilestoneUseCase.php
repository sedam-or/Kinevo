<?php

namespace App\Application\Milestones;

use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use Carbon\CarbonImmutable;

/**
 * Updates editable fields of an existing milestone.
 */
final readonly class UpdateMilestoneUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
    ) {}

    public function __invoke(
        int $userId,
        int $milestoneId,
        ?string $title = null,
        ?string $description = null,
        ?int $sequence = null,
        ?CarbonImmutable $targetDate = null,
        ?int $estimatedMinutes = null,
    ): Milestone {
        $milestone = (new GetMilestoneUseCase($this->milestones))($userId, $milestoneId);

        if ($title !== null) {
            $milestone = $milestone->withTitle($title);
        }
        if ($description !== null) {
            $milestone = $milestone->withDescription($description);
        }
        if ($sequence !== null) {
            $milestone = $milestone->withSequence($sequence);
        }
        if ($targetDate !== null) {
            $milestone = $milestone->withTargetDate($targetDate);
        }
        if ($estimatedMinutes !== null) {
            $milestone = $milestone->withEstimatedMinutes($estimatedMinutes);
        }

        return $this->milestones->update($milestone);
    }
}
