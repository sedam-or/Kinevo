<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveContextApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => 'Task',
            'status' => 'scheduled',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    public function test_context_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/adaptive/context')->assertStatus(401);
        $this->postJson('/api/v1/adaptive/context', ['energy_level' => 5])->assertStatus(401);
        $this->getJson('/api/v1/adaptive/burnout')->assertStatus(401);
    }

    public function test_check_in_can_be_recorded(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/adaptive/context', [
            'energy_level' => 8,
            'stress_level' => 3,
        ])->assertStatus(201)
            ->assertJsonPath('observation.energy_level', 8)
            ->assertJsonPath('observation.stress_level', 3)
            ->assertJsonPath('observation.task_id', null);

        $this->assertDatabaseHas('adaptive_context', [
            'user_id' => $user->id,
            'energy_level' => 8,
            'stress_level' => 3,
        ]);
    }

    public function test_check_in_requires_at_least_one_signal(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/adaptive/context', [])
            ->assertStatus(422);
    }

    public function test_signal_levels_are_bounded(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/adaptive/context', ['energy_level' => 11])
            ->assertStatus(422);

        $this->assertDatabaseCount('adaptive_context', 0);
    }

    public function test_check_in_task_must_be_owned(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $foreignTask = $this->createTask($other->id);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/adaptive/context', [
            'task_id' => $foreignTask->id,
            'task_difficulty' => 6,
        ])->assertStatus(404);

        $this->assertDatabaseCount('adaptive_context', 0);
    }

    public function test_check_in_can_be_scoped_to_owned_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson('/api/v1/adaptive/context', [
            'task_id' => $task->id,
            'task_difficulty' => 7,
            'skill_familiarity' => 3,
        ])->assertStatus(201)
            ->assertJsonPath('observation.task_id', $task->id);
    }

    public function test_check_ins_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $this->withToken($token)->postJson('/api/v1/adaptive/context', ['energy_level' => 8])->assertStatus(201);
        $this->withToken($token)->postJson('/api/v1/adaptive/context', ['energy_level' => 6])->assertStatus(201);

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/adaptive/context')
            ->assertStatus(200)
            ->assertJsonCount(0, 'observations');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/adaptive/context')
            ->assertStatus(200)
            ->assertJsonCount(2, 'observations')
            ->assertJsonPath('observations.0.energy_level', 6);
    }

    public function test_burnout_signal_starts_inactive(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/adaptive/burnout')
            ->assertStatus(200)
            ->assertJsonPath('active', false)
            ->assertJsonPath('sample_count', 0);
    }

    public function test_burnout_signal_activates_on_sustained_high_stress_low_energy(): void
    {
        [$user, $token] = $this->userWithToken();

        foreach ([8, 9, 8] as $stress) {
            $this->withToken($token)->postJson('/api/v1/adaptive/context', [
                'energy_level' => 3,
                'stress_level' => $stress,
            ])->assertStatus(201);
        }

        $this->withToken($token)->getJson('/api/v1/adaptive/burnout')
            ->assertStatus(200)
            ->assertJsonPath('active', true)
            ->assertJsonPath('sample_count', 3);
    }
}
