<?php

namespace App\Application\Programs;

use App\Domain\Programs\Contracts\ProgramRepository;
use App\Domain\Programs\Program;

/**
 * Lists all programs of a user.
 */
final readonly class ListProgramsUseCase
{
    public function __construct(
        private ProgramRepository $programs,
    ) {}

    /**
     * @return array<int, Program>
     */
    public function __invoke(int $userId): array
    {
        return $this->programs->listForUser($userId);
    }
}
