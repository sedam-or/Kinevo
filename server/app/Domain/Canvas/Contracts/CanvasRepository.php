<?php

namespace App\Domain\Canvas\Contracts;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\CanvasDocument;

interface CanvasRepository
{
    public function findForUser(int $userId, int $canvasId): ?Canvas;

    public function findDocumentForCanvas(int $canvasId): ?CanvasDocument;

    public function listForUser(int $userId): array;

    public function create(int $userId, Canvas $canvas): Canvas;

    public function createDocument(CanvasDocument $document): CanvasDocument;

    public function updateDocument(CanvasDocument $document, int $baseVersion): CanvasDocument;
}
