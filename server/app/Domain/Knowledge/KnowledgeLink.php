<?php

namespace App\Domain\Knowledge;

use App\Domain\Knowledge\ValueObjects\KnowledgeLinkType;
use App\Domain\Knowledge\ValueObjects\KnowledgeTargetType;
use InvalidArgumentException;

/**
 * Explicit domain relationship between a knowledge item and a domain object
 * (FR-54, SRS §10.5). Immutable value semantics.
 */
final class KnowledgeLink
{
    public const SOURCE_NOTE = 'note';

    public const SOURCE_CANVAS = 'canvas';

    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $sourceType,
        public readonly int $sourceId,
        public readonly KnowledgeTargetType $targetType,
        public readonly int $targetId,
        public readonly KnowledgeLinkType $linkType,
    ) {}

    /**
     * @param  string  $sourceType  source knowledge-item type (self::SOURCE_NOTE)
     */
    public static function create(
        int $userId,
        string $sourceType,
        int $sourceId,
        KnowledgeTargetType $targetType,
        int $targetId,
        KnowledgeLinkType $linkType,
    ): self {
        if (trim($sourceType) === '') {
            throw new InvalidArgumentException('Knowledge link source type is required.');
        }
        if ($sourceId < 1) {
            throw new InvalidArgumentException('Knowledge link source id is invalid.');
        }
        if ($targetId < 1) {
            throw new InvalidArgumentException('Knowledge link target id is invalid.');
        }

        return new self(
            0,
            $userId,
            trim($sourceType),
            $sourceId,
            $targetType,
            $targetId,
            $linkType,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->userId,
            $this->sourceType,
            $this->sourceId,
            $this->targetType,
            $this->targetId,
            $this->linkType,
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
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'target_type' => $this->targetType->value,
            'target_id' => $this->targetId,
            'link_type' => $this->linkType->value,
        ];
    }
}
