<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\Note;

final class SearchNotesUseCase
{
    public function __construct(
        private readonly NoteRepository $repository,
    ) {}

    /**
     * @return array<int, Note>
     */
    public function __invoke(int $userId, string $query): array
    {
        if (trim($query) === '') {
            return [];
        }

        return $this->repository->searchForUser($userId, trim($query));
    }
}
