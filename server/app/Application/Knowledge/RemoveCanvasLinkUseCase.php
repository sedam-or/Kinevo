<?php

namespace App\Application\Knowledge;

use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use InvalidArgumentException;

final class RemoveCanvasLinkUseCase
{
    public function __construct(
        private readonly CanvasRepository $canvases,
        private readonly KnowledgeLinkRepository $links,
    ) {}

    public function __invoke(int $userId, int $canvasId, int $linkId): void
    {
        $canvas = $this->canvases->findForUser($userId, $canvasId);
        if ($canvas === null) {
            throw new InvalidArgumentException("Canvas not found: {$canvasId}");
        }

        $link = $this->links->findForUser($userId, $linkId);
        if ($link === null || $link->sourceId !== $canvasId) {
            throw new InvalidArgumentException("Knowledge link not found: {$linkId}");
        }

        $this->links->remove($userId, $linkId);
    }
}
