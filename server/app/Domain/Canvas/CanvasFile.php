<?php

namespace App\Domain\Canvas;

use InvalidArgumentException;

/**
 * CanvasFile — metadata for a binary asset attached to a canvas (SRS §7.5).
 * Binary payloads live in object storage; this row references them by a stable
 * application-owned storage path. The domain owns identity/metadata only.
 */
final class CanvasFile
{
    public function __construct(
        public readonly int $id,
        public readonly int $canvasId,
        public readonly string $storagePath,
        public readonly string $contentType,
        public readonly int $sizeBytes,
        public readonly ?string $sha256,
    ) {}

    public static function create(
        int $canvasId,
        string $storagePath,
        string $contentType,
        int $sizeBytes,
        ?string $sha256 = null,
    ): self {
        if (trim($storagePath) === '') {
            throw new InvalidArgumentException('Canvas file storage path is required.');
        }
        if (trim($contentType) === '') {
            throw new InvalidArgumentException('Canvas file content type is required.');
        }
        if ($sizeBytes < 0) {
            throw new InvalidArgumentException('Canvas file size cannot be negative.');
        }

        return new self(
            0,
            $canvasId,
            trim($storagePath),
            trim($contentType),
            $sizeBytes,
            $sha256,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->canvasId,
            $this->storagePath,
            $this->contentType,
            $this->sizeBytes,
            $this->sha256,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'canvas_id' => $this->canvasId,
            'storage_path' => $this->storagePath,
            'content_type' => $this->contentType,
            'size_bytes' => $this->sizeBytes,
            'sha256' => $this->sha256,
        ];
    }
}
