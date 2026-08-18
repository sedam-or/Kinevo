<?php

namespace App\Application\Knowledge;

use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\Contracts\NoteRepository;
use InvalidArgumentException;

final class RemoveNoteLinkUseCase
{
    public function __construct(
        private readonly NoteRepository $notes,
        private readonly KnowledgeLinkRepository $links,
    ) {}

    public function __invoke(int $userId, int $noteId, int $linkId): void
    {
        $note = $this->notes->findForUser($userId, $noteId);
        if ($note === null) {
            throw new InvalidArgumentException("Note not found: {$noteId}");
        }

        $link = $this->links->findForUser($userId, $linkId);
        if ($link === null || $link->sourceId !== $noteId) {
            throw new InvalidArgumentException("Knowledge link not found: {$linkId}");
        }

        $this->links->remove($userId, $linkId);
    }
}
