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
    ): Note {
        $note = Note::create($userId, $title, $documentJson, $markdownCache, $plainTextCache);

        return $this->repository->create($userId, $note);
    }
}
