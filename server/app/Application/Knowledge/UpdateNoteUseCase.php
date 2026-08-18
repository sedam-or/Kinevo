<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;
use InvalidArgumentException;

final class UpdateNoteUseCase
{
    public function __construct(
        private readonly NoteRepository $repository,
    ) {}

    public function __invoke(
        int $userId,
        int $noteId,
        int $baseVersion,
        ?string $title = null,
        ?array $documentJson = null,
        ?string $markdownCache = null,
        ?string $plainTextCache = null,
    ): Note {
        $note = $this->repository->findForUser($userId, $noteId);

        if ($note === null) {
            throw new InvalidArgumentException("Note not found: {$noteId}");
        }

        if ($title !== null) {
            $note = $note->withTitle($title);
        }

        if ($documentJson !== null || $markdownCache !== null || $plainTextCache !== null) {
            $note = $note->withContent($documentJson, $markdownCache, $plainTextCache);
        }

        return $this->repository->update($note, $baseVersion);
    }
}
