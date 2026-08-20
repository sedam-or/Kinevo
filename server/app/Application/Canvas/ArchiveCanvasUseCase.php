<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\Contracts\CanvasRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class ArchiveCanvasUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    public function __invoke(int $userId, int $canvasId): Canvas
    {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        $canvas = $canvas->archive(new DateTimeImmutable);

        return $this->repository->update($canvas);
    }
}
