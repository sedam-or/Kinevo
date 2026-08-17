<?php

namespace App\Application\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use App\Domain\Programs\ValueObjects\ProgramStatus;

/**
 * Applies an explicit FR-22 lifecycle transition (Active/Paused/Completed/Dropped).
 */
final readonly class SetProgramStatusUseCase
{
    public function __construct(
        private ProgramRepository $programs,
    ) {}

    public function __invoke(
        int $userId,
        int $programId,
        ProgramStatus $status,
    ): Program {
        $program = (new GetProgramUseCase($this->programs))($userId, $programId);

        $updated = $program->withStatus($status);

        return $this->programs->update($updated);
    }
}
