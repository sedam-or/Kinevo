<?php

namespace App\Application\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramWorkloadType;

/**
 * Creates a sustained workstream (FR-26 intake). Workload type validated by the entity.
 */
final readonly class CreateProgramUseCase
{
    public function __construct(
        private ProgramRepository $programs,
    ) {}

    public function __invoke(
        int $userId,
        string $name,
        ?string $description,
        ?string $category,
        ProgramWorkloadType $workloadType,
        ?int $weeklyTargetMinutes = null,
        ?int $minWeeklyMinutes = null,
        ?int $maxWeeklyMinutes = null,
        int $priorityTier = 3,
    ): Program {
        $program = Program::create(
            $userId,
            $name,
            $description,
            $category,
            $workloadType,
            $weeklyTargetMinutes,
            $minWeeklyMinutes,
            $maxWeeklyMinutes,
            $priorityTier,
        );

        return $this->programs->create($userId, $program);
    }
}
