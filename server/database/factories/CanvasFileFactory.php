<?php

namespace Database\Factories;

use App\Models\Canvas;
use App\Models\CanvasFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CanvasFile>
 */
class CanvasFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'canvas_id' => Canvas::factory(),
            'storage_path' => 'canvases/'.$this->faker->uuid.'.png',
            'content_type' => 'image/png',
            'size_bytes' => $this->faker->numberBetween(1024, 1048576),
            'sha256' => str_repeat('a', 64),
        ];
    }
}
