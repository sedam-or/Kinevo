<?php

namespace App\Domain\Knowledge\Contracts;

use App\Domain\Knowledge\Note;

interface NoteRepository
{
    public function findForUser(int $userId, int $noteId): ?Note;

    /**
     * @return array<int, Note>
     */
    public function listForUser(int $userId): array;

    /**
     * TASK-P19-014 — workspace-scoped listing.
     *
     * @return array<int, Note>
     */
    public function listForUserInWorkspace(int $userId, int $workspaceId): array;

    public function create(int $userId, Note $note): Note;

    public function update(Note $note, int $baseVersion): Note;

    /**
     * @return array<int, Note>
     */
    public function searchForUser(int $userId, string $query): array;
}
