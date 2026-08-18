<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\Contracts\NoteRepository;
use App\Domain\Knowledge\KnowledgeLink;
use InvalidArgumentException;

final class ListNoteLinksUseCase
{
    public function __construct(
        private readonly NoteRepository $notes,
        private readonly KnowledgeLinkRepository $links,
    ) {}

    /**
     * @return list<KnowledgeLink>
     */
    public function __invoke(int $userId, int $noteId): array
    {
        $note = $this->notes->findForUser($userId, $noteId);
        if ($note === null) {
            throw new InvalidArgumentException("Note not found: {$noteId}");
        }

        return $this->links->listForSource($userId, KnowledgeLink::SOURCE_NOTE, $noteId);
    }
}
