<?php

namespace App\Domain\Programs\Contracts;

use App\Domain\Programs\Program;

interface ProgramRepository
{
    public function findForUser(int $userId, int $programId): ?Program;

    /**
     * @return array<int, Program>
     */
    public function listForUser(int $userId): array;

    /**
     * TASK-P19-012 — workspace-scoped listing.
     *
     * @return array<int, Program>
     */
    public function listForUserInWorkspace(int $userId, int $workspaceId): array;

    public function create(int $userId, Program $program): Program;

    public function update(Program $program): Program;
}
