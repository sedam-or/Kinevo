<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\CanvasDocument;
use App\Domain\Canvas\Contracts\CanvasRepository;
use InvalidArgumentException;

final class GetCanvasUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    /**
     * @return array{canvas: Canvas, document: CanvasDocument|null}
     */
    public function __invoke(int $userId, int $canvasId): array
    {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        $document = $this->repository->findDocumentForCanvas($canvasId);

        return ['canvas' => $canvas, 'document' => $document];
    }
}
