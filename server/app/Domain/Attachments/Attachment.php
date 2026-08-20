<?php

namespace App\Domain\Attachments;

use Carbon\CarbonImmutable;

/**
 * An evidence attachment associated with a task (FR-43). Stores only file
 * metadata in the record; the bytes live on a private (non-world-readable)
 * disk under a generated stored name (SRS line 1653).
 */
final class Attachment
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly int $taskId,
        public readonly string $filename,
        public readonly string $storedName,
        public readonly string $disk,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly string $sha256,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {}

    public static function create(
        int $userId,
        int $taskId,
        string $filename,
        string $storedName,
        string $disk,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        ?CarbonImmutable $createdAt = null,
    ): self {
        return new self(
            null,
            $userId,
            $taskId,
            $filename,
            $storedName,
            $disk,
            $mimeType,
            $sizeBytes,
            $sha256,
            $createdAt ?? CarbonImmutable::now(),
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->taskId,
            $this->filename,
            $this->storedName,
            $this->disk,
            $this->mimeType,
            $this->sizeBytes,
            $this->sha256,
            $this->createdAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->taskId,
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
            'created_at' => $this->createdAt?->toIso8601String(),
        ];
    }
}
