<?php

namespace Tests\Feature\Api;

use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_goals_require_authentication(): void
    {
        $this->getJson('/api/v1/goals')->assertStatus(401);
        $this->postJson('/api/v1/goals', [])->assertStatus(401);
    }

    public function test_goal_can_be_created(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Learn Laravel in depth',
            'horizon' => 'yearly',
            'target_date' => '2026-12-31',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('goal.title', 'Learn Laravel in depth')
            ->assertJsonPath('goal.horizon', 'yearly')
            ->assertJsonPath('goal.status', 'draft')
            ->assertJsonPath('goal.priority_tier', 3)
            ->assertJsonPath('goal.target_date', '2026-12-31')
            ->assertJsonPath('goal.progress', 0);

        $this->assertDatabaseHas('goals', [
            'user_id' => $user->id,
            'title' => 'Learn Laravel in depth',
            'horizon' => 'yearly',
        ]);
    }

    public function test_goal_creation_validates_input(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => '',
            'horizon' => 'bimonthly',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Goal',
            'horizon' => 'yearly',
            'start_date' => '2026-12-31',
            'target_date' => '2026-01-01',
        ])->assertStatus(422);
    }

    public function test_goals_are_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $other->id,
            'title' => 'Not mine',
            'horizon' => 'custom',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->getJson("/api/v1/goals/{$goal->id}")->assertStatus(404);
        $this->withToken($token)->putJson("/api/v1/goals/{$goal->id}", ['title' => 'Hijack'])->assertStatus(404);
        $this->withToken($token)->getJson('/api/v1/goals')->assertJsonCount(0, 'goals');
    }

    public function test_goal_can_be_listed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'First',
            'horizon' => 'yearly',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
        Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Second',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 2,
            'progress_mode' => 'derived',
            'progress' => 10,
        ]);

        $this->withToken($token)->getJson('/api/v1/goals')
            ->assertStatus(200)
            ->assertJsonCount(2, 'goals');
    }

    public function test_goal_can_be_updated(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Old title',
            'horizon' => 'custom',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->putJson("/api/v1/goals/{$goal->id}", [
            'title' => 'New title',
            'priority_tier' => 1,
        ])->assertStatus(200)
            ->assertJsonPath('goal.title', 'New title')
            ->assertJsonPath('goal.priority_tier', 1)
            ->assertJsonPath('goal.horizon', 'custom');
    }

    public function test_goal_status_lifecycle_transitions(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Ship a feature',
            'horizon' => 'monthly',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/status", ['status' => 'active'])
            ->assertStatus(200)
            ->assertJsonPath('goal.status', 'active');

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/status", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('goal.status', 'completed');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Directly complete',
            'horizon' => 'custom',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/status", ['status' => 'completed'])
            ->assertStatus(422);
    }

    public function test_goal_deadline_is_exposed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Research project',
            'horizon' => 'custom',
            'target_date' => '2026-12-15',
        ])->assertStatus(201);

        $this->assertDatabaseHas('goals', ['title' => 'Research project', 'target_date' => '2026-12-15']);
    }

    public function test_sixth_active_yearly_goal_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        for ($i = 1; $i <= 5; $i++) {
            Goal::query()->create([
                'user_id' => $user->id,
                'title' => "Yearly {$i}",
                'horizon' => 'yearly',
                'status' => 'active',
                'priority_tier' => 3,
                'progress_mode' => 'derived',
                'progress' => 0,
            ]);
        }

        $this->withToken($token)->postJson('/api/v1/goals', [
            'title' => 'Over limit',
            'horizon' => 'yearly',
        ])->assertStatus(422);
    }
}
