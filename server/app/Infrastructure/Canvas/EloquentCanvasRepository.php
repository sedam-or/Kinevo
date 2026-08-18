<?php

namespace App\Infrastructure\Canvas;

use App\Domain\Canvas\Canvas;
use App\Domain\Canvas\CanvasDocument;
use App\Domain\Canvas\CanvasFile;
use App\Domain\Canvas\CanvasVersionConflict;
use App\Domain\Canvas\Contracts\CanvasRepository;
use App\Models\Canvas as CanvasModel;
use App\Models\CanvasDocument as CanvasDocumentModel;
use App\Models\CanvasFile as CanvasFileModel;

final class EloquentCanvasRepository implements CanvasRepository
{
    public function findForUser(int $userId, int $canvasId): ?Canvas
    {
        $model = CanvasModel::query()->where('user_id', $userId)->find($canvasId);

        return $model === null ? null : $this->toDomain($model);
    }

    public function findDocumentForCanvas(int $canvasId): ?CanvasDocument
    {
        $model = CanvasDocumentModel::query()->where('canvas_id', $canvasId)->first();

        return $model === null ? null : $this->toDocumentDomain($model);
    }

    public function listForUser(int $userId): array
    {
        return CanvasModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function create(int $userId, Canvas $canvas): Canvas
    {
        $model = CanvasModel::query()->create([
            'user_id' => $userId,
            'title' => $canvas->title,
            'goal_id' => $canvas->goalId,
            'milestone_id' => $canvas->milestoneId,
            'program_id' => $canvas->programId,
            'task_id' => $canvas->taskId,
            'version' => 1,
        ]);

        return $this->toDomain($model);
    }

    public function createDocument(CanvasDocument $document): CanvasDocument
    {
        $model = CanvasDocumentModel::query()->create([
            'canvas_id' => $document->canvasId,
            'schema_version' => $document->schemaVersion,
            'scene_json' => $document->sceneJson,
            'version' => 1,
        ]);

        return $this->toDocumentDomain($model);
    }

    public function updateDocument(CanvasDocument $document, int $baseVersion): CanvasDocument
    {
        $model = CanvasDocumentModel::query()
            ->where('canvas_id', $document->canvasId)
            ->where('version', $baseVersion)
            ->first();

        if ($model === null) {
            $current = CanvasDocumentModel::query()->where('canvas_id', $document->canvasId)->first();
            $actualVersion = $current !== null ? $current->version : 0;
            throw new CanvasVersionConflict($baseVersion, $actualVersion);
        }

        $model->update([
            'scene_json' => $document->sceneJson,
            'version' => $document->version,
        ]);
        $model->refresh();

        return $this->toDocumentDomain($model);
    }

    public function listFilesForCanvas(int $canvasId): array
    {
        return CanvasFileModel::query()
            ->where('canvas_id', $canvasId)
            ->orderByDesc('created_at')
            ->get()
            ->map($this->toFileDomain(...))
            ->all();
    }

    public function createFile(CanvasFile $file): CanvasFile
    {
        $model = CanvasFileModel::query()->create([
            'canvas_id' => $file->canvasId,
            'storage_path' => $file->storagePath,
            'content_type' => $file->contentType,
            'size_bytes' => $file->sizeBytes,
            'sha256' => $file->sha256,
        ]);

        return $this->toFileDomain($model);
    }

    private function toDomain(CanvasModel $model): Canvas
    {
        return new Canvas(
            $model->id,
            $model->user_id,
            $model->title,
            $model->goal_id,
            $model->milestone_id,
            $model->program_id,
            $model->task_id,
            $model->version,
        );
    }

    private function toDocumentDomain(CanvasDocumentModel $model): CanvasDocument
    {
        return new CanvasDocument(
            $model->id,
            $model->canvas_id,
            $model->schema_version,
            $model->scene_json,
            $model->version,
        );
    }

    private function toFileDomain(CanvasFileModel $model): CanvasFile
    {
        return new CanvasFile(
            $model->id,
            $model->canvas_id,
            $model->storage_path,
            $model->content_type,
            $model->size_bytes,
            $model->sha256,
        );
    }
}
