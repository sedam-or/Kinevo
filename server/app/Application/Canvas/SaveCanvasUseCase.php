<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\CanvasDocument;
use App\Domain\Canvas\Contracts\CanvasRepository;
use InvalidArgumentException;

final class SaveCanvasUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    /**
     * @param  array  $sceneJson  Excalidraw scene JSON
     */
    public function __invoke(int $userId, int $canvasId, int $baseVersion, array $sceneJson): CanvasDocument
    {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        $document = CanvasDocument::create(
            $canvasId,
            $sceneJson,
        );

        $document = $document->withScene($sceneJson, $baseVersion);

        $existing = $this->repository->findDocumentForCanvas($canvasId);

        if ($existing === null) {
            return $this->repository->createDocument($document);
        }

        return $this->repository->updateDocument($document, $baseVersion);
    }
}
