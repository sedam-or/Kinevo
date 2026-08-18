<?php

namespace App\Domain\Canvas\Contracts;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\CanvasDocument;
use App\Domain\Canvas\CanvasFile;

interface CanvasRepository
{
    public function findForUser(int $userId, int $canvasId): ?Canvas;

    public function findDocumentForCanvas(int $canvasId): ?CanvasDocument;

    public function listForUser(int $userId): array;

    public function create(int $userId, Canvas $canvas): Canvas;

    public function createDocument(CanvasDocument $document): CanvasDocument;

    public function updateDocument(CanvasDocument $document, int $baseVersion): CanvasDocument;

    /**
     * @return array<int, CanvasFile>
     */
    public function listFilesForCanvas(int $canvasId): array;

    public function createFile(CanvasFile $file): CanvasFile;
}
