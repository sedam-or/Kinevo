<?php

namespace App\Domain\Knowledge;

use InvalidArgumentException;

/**
 * Note aggregate — a knowledge artifact containing structured rich text (FR-53,
 * SRS §7.4 notes table). Immutable value semantics: state changes return a new
 * instance with an incremented version.
 */
final class Note
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $title,
        public readonly ?array $documentJson,
        public readonly ?string $markdownCache,
        public readonly ?string $plainTextCache,
        public readonly int $version,
    ) {}

    public static function create(
        int $userId,
        string $title,
        ?array $documentJson = null,
        ?string $markdownCache = null,
        ?string $plainTextCache = null,
    ): self {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Note title is required.');
        }

        return new self(
            0,
            $userId,
            trim($title),
            $documentJson,
            $markdownCache,
            $plainTextCache,
            1,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->title,
            $this->documentJson,
            $this->markdownCache,
            $this->plainTextCache,
            $this->version,
        );
    }

    public function withTitle(string $title): self
    {
        if (trim($title) === '') {
            throw new InvalidArgumentException('Note title is required.');
        }

        return new self(
            $this->id,
            $this->userId,
            trim($title),
            $this->documentJson,
            $this->markdownCache,
            $this->plainTextCache,
            $this->version + 1,
        );
    }

    public function withContent(
        ?array $documentJson = null,
        ?string $markdownCache = null,
        ?string $plainTextCache = null,
    ): self {
        return new self(
            $this->id,
            $this->userId,
            $this->title,
            $documentJson,
            $markdownCache,
            $plainTextCache,
            $this->version + 1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'document_json' => $this->documentJson,
            'markdown_cache' => $this->markdownCache,
            'plain_text_cache' => $this->plainTextCache,
            'version' => $this->version,
        ];
    }
}
