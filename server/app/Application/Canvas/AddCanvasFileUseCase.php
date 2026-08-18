<?php

namespace App\Application\Canvas;

use App\Domain\Canvas\CanvasFile;
use App\Domain\Canvas\Contracts\CanvasRepository;
use InvalidArgumentException;

final class AddCanvasFileUseCase
{
    public function __construct(
        private readonly CanvasRepository $repository,
    ) {}

    public function __invoke(
        int $userId,
        int $canvasId,
        string $storagePath,
        string $contentType,
        int $sizeBytes,
        ?string $sha256 = null,
    ): CanvasFile {
        $canvas = $this->repository->findForUser($userId, $canvasId);

        if ($canvas === null) {
            throw new InvalidArgumentException('Canvas not found.');
        }

        $file = CanvasFile::create(
            $canvasId,
            $storagePath,
            $contentType,
            $sizeBytes,
            $sha256,
        );

        return $this->repository->createFile($file);
    }
}
