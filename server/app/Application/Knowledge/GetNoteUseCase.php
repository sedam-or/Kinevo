<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;
use InvalidArgumentException;

final class GetNoteUseCase
{
    public function __construct(
        private readonly NoteRepository $repository,
    ) {}

    public function __invoke(int $userId, int $noteId): Note
    {
        $note = $this->repository->findForUser($userId, $noteId);

        if ($note === null) {
            throw new InvalidArgumentException("Note not found: {$noteId}");
        }

        return $note;
    }
}
