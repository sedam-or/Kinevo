<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\Contracts\CanvasRepository;
use InvalidArgumentException;

final class RenameCanvasUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    public function __invoke(int $userId, int $canvasId, string $title): Canvas
    {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        $canvas = $canvas->withTitle($title);

        return $this->repository->update($canvas);
    }
}
