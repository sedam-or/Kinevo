<?php

namespace App\Application\Milestones;

use App\Domain\Milestones\Contracts\MilestoneRepository;
use App\Domain\Milestones\Milestone;
use InvalidArgumentException;

/**
 * Returns a single milestone scoped to the owner. Not found → InvalidArgumentException.
 */
final readonly class GetMilestoneUseCase
{
    public function __construct(
        private MilestoneRepository $milestones,
    ) {}

    public function __invoke(int $userId, int $milestoneId): Milestone
    {
        $milestone = $this->milestones->findForUser($userId, $milestoneId);

        if ($milestone === null) {
            throw new InvalidArgumentException('Milestone not found.');
        }

        return $milestone;
    }
}
