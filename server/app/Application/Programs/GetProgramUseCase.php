<?php

namespace App\Application\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;
use InvalidArgumentException;

/**
 * Returns a single program scoped to the owner. Not found → InvalidArgumentException.
 */
final readonly class GetProgramUseCase
{
    public function __construct(
        private ProgramRepository $programs,
    ) {}

    public function __invoke(int $userId, int $programId): Program
    {
        $program = $this->programs->findForUser($userId, $programId);

        if ($program === null) {
            throw new InvalidArgumentException('Program not found.');
        }

        return $program;
    }
}
