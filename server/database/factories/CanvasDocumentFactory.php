<?php

namespace Database\Factories;

use App\Models\Canvas;
use App\Models\CanvasDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanvasDocument>
 */
class CanvasDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'schema_version' => 1,
            'scene_json' => ['type' => 'excalidraw', 'elements' => [], 'appState' => []],
            'version' => 1,
        ];
    }
}
