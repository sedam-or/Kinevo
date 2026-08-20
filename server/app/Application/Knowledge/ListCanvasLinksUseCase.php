<?php

namespace App\Application\Knowledge;

use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Domain\Knowledge\Contracts\KnowledgeLinkRepository;
use App\Domain\Knowledge\KnowledgeLink;
use InvalidArgumentException;

final class ListCanvasLinksUseCase
{
    public function __construct(
        private readonly CanvasRepository $canvases,
        private readonly KnowledgeLinkRepository $links,
    ) {}

    /**
     * @return list<KnowledgeLink>
     */
    public function __invoke(int $userId, int $canvasId): array
    {
        $canvas = $this->canvases->findForUser($userId, $canvasId);
        if ($canvas === null) {
            throw new InvalidArgumentException("Canvas not found: {$canvasId}");
        }

        return $this->links->listForSource($userId, KnowledgeLink::SOURCE_CANVAS, $canvasId);
    }
}
