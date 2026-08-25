<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;

final class CreateNoteUseCase
{
    public function __construct(
        private readonly NoteRepository $repository,
    ) {}

    public function __invoke(
        int $userId,
        string $title,
        ?array $documentJson = null,
        ?string $markdownCache = null,
        ?string $plainTextCache = null,
        ?int $workspaceId = null,
    ): Note {
        // TASK-P19-014 — notes default to the active workspace context.
        if ($workspaceId !== null) {
            return $this->repository->create($userId, Note::create($userId, $title, $documentJson, $markdownCache, $plainTextCache)->withWorkspace($workspaceId));
        }

        return $this->repository->create($userId, Note::create($userId, $title, $documentJson, $markdownCache, $plainTextCache));
    }
}
