<?php

namespace App\Application\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;

/**
 * Updates editable fields of an existing program (FR-22/FR-26).
 */
final readonly class UpdateProgramUseCase
{
    public function __construct(
        private ProgramRepository $programs,
    ) {}

    public function __invoke(
        int $userId,
        int $programId,
        ?string $name = null,
        ?string $description = null,
        ?string $category = null,
        ?ProgramWorkloadType $workloadType = null,
        ?int $weeklyTargetMinutes = null,
        ?int $minWeeklyMinutes = null,
        ?int $maxWeeklyMinutes = null,
        ?int $priorityTier = null,
    ): Program {
        $program = (new GetProgramUseCase($this->programs))($userId, $programId);

        if ($name !== null) {
            $program = $program->withName($name);
        }
        if ($description !== null) {
            $program = $program->withDescription($description);
        }
        if ($category !== null) {
            $program = $program->withCategory($category);
        }
        if ($workloadType !== null) {
            $program = $program->withWorkload($workloadType, $weeklyTargetMinutes, $minWeeklyMinutes, $maxWeeklyMinutes);
        }
        if ($priorityTier !== null) {
            $program = $program->withPriorityTier($priorityTier);
        }

        return $this->programs->update($program);
    }
}
