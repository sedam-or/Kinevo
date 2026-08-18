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
    public function __invoke(int $userId): array
    {
        return $this->repository->listForUser($userId);
    }
}
