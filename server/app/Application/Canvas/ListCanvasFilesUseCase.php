<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\CanvasFile;
use App\Domain\Canvas\Contracts\CanvasRepository;
use InvalidArgumentException;

final class ListCanvasFilesUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    /**
     * @return array<int, CanvasFile>
     */
    public function __invoke(int $userId, int $canvasId): array
    {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        return $this->repository->listFilesForCanvas($canvasId);
    }
}
