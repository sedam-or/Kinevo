<?php

namespace App\Domain\Canvas;

final class CanvasDocument
{
    public const CURRENT_SCHEMA_VERSION = 1;

    public function __construct(
        public readonly int $id,
        public readonly int $canvasId,
        public readonly int $schemaVersion,
        public readonly array $sceneJson,
        public readonly int $version,
    ) {}

    public static function create(
        int $canvasId,
        array $sceneJson,
        int $schemaVersion = self::CURRENT_SCHEMA_VERSION,
    ): self {
        return new self(
            0,
            $canvasId,
            $schemaVersion,
            $sceneJson,
            1,
        );
    }

    public function withScene(array $sceneJson, int $baseVersion): self
    {
        return new self(
            $this->id,
            $this->canvasId,
            $this->schemaVersion,
            $sceneJson,
            $baseVersion + 1,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'canvas_id' => $this->canvasId,
            'schema_version' => $this->schemaVersion,
            'scene_json' => $this->sceneJson,
            'version' => $this->version,
        ];
    }
}
