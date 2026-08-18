<?php

namespace Database\Factories;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Canvas>
 */
class CanvasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'goal_id' => null,
            'milestone_id' => null,
            'program_id' => null,
            'task_id' => null,
            'version' => 1,
        ];
    }
}
