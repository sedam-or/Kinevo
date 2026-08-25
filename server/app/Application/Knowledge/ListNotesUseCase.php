<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;

final class ListNotesUseCase
{
    public function __construct(
        private readonly NoteRepository $repository,
    ) {}

    /**
     * @return array<int, Note>
     */
    public function __invoke(int $userId, ?int $workspaceId = null): array
    {
        // TASK-P19-014 — workspace filter; null = global view.
        if ($workspaceId !== null) {
            return $this->repository->listForUserInWorkspace($userId, $workspaceId);
        }

        return $this->repository->listForUser($userId);
    }
}
