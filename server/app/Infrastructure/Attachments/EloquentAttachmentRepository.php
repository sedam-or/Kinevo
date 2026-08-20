<?php

namespace App\Infrastructure\Attachments;

use App\Domain\Attachments\Attachment;
use App\Domain\Attachments\Contracts\AttachmentRepository;
use App\Models\Attachment as AttachmentModel;
use Carbon\CarbonImmutable;

final class EloquentAttachmentRepository implements AttachmentRepository
{
    public function create(Attachment $attachment): Attachment
    {
        $model = AttachmentModel::query()->create([
            'user_id' => $attachment->userId,
            'task_id' => $attachment->taskId,
            'filename' => $attachment->filename,
            'stored_name' => $attachment->storedName,
            'disk' => $attachment->disk,
            'mime_type' => $attachment->mimeType,
            'size_bytes' => $attachment->sizeBytes,
            'sha256' => $attachment->sha256,
        ]);

        return $this->toDomain($model);
    }

    public function listForTask(int $userId, int $taskId): array
    {
        return AttachmentModel::query()
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->orderByDesc('id')
            ->get()
            ->map($this->toDomain(...))
            ->all();
    }

    public function findForUser(int $userId, int $attachmentId): ?Attachment
    {
        $model = AttachmentModel::query()
            ->where('user_id', $userId)
            ->where('id', $attachmentId)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function countForTask(int $userId, int $taskId): int
    {
        return AttachmentModel::query()
            ->where('user_id', $userId)
            ->where('task_id', $taskId)
            ->count();
    }

    public function delete(int $attachmentId): void
    {
        AttachmentModel::query()->where('id', $attachmentId)->delete();
    }

    private function toDomain(AttachmentModel $model): Attachment
    {
        return new Attachment(
            $model->id,
            $model->user_id,
            $model->task_id,
            $model->filename,
            $model->stored_name,
            $model->disk,
            $model->mime_type,
            $model->size_bytes,
            $model->sha256,
            CarbonImmutable::parse($model->created_at),
        );
    }
}
