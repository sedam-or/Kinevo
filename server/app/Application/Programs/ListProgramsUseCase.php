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
     * TASK-P19-012 — workspace filter; null = global view.
     *
     * @return array<int, Program>
     */
    public function __invoke(int $userId, ?int $workspaceId = null): array
    {
        if ($workspaceId !== null) {
            return $this->programs->listForUserInWorkspace($userId, $workspaceId);
        }

        return $this->programs->listForUser($userId);
    }
}
