<?php

namespace Tests\Feature\Api;

use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    public function test_programs_require_authentication(): void
    {
        $this->getJson('/api/v1/programs')->assertStatus(401);
        $this->postJson('/api/v1/programs', [])->assertStatus(401);
    }

    public function test_structured_program_can_be_created(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Daily writing',
            'description' => 'Sustained writing habit',
            'category' => 'Growth',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 300,
        ])->assertStatus(201)
            ->assertJsonPath('program.name', 'Daily writing')
            ->assertJsonPath('program.workload_type', 'structured')
            ->assertJsonPath('program.weekly_target_minutes', 300)
            ->assertJsonPath('program.status', 'active')
            ->assertJsonPath('program.priority_tier', 3);

        $this->assertDatabaseHas('programs', [
            'user_id' => $user->id,
            'name' => 'Daily writing',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 300,
        ]);
    }

    public function test_range_program_can_be_created_with_min_max(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Strength training',
            'category' => 'Fitness',
            'workload_type' => 'range',
            'min_weekly_minutes' => 60,
            'max_weekly_minutes' => 120,
        ])->assertStatus(201)
            ->assertJsonPath('program.min_weekly_minutes', 60)
            ->assertJsonPath('program.max_weekly_minutes', 120);
    }

    public function test_flexible_program_can_be_created_without_weekly_target(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Reading habit',
            'workload_type' => 'flexible',
        ])->assertStatus(201)
            ->assertJsonPath('program.workload_type', 'flexible')
            ->assertJsonPath('program.weekly_target_minutes', null);
    }

    public function test_program_creation_validates_workload_rules(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Broken structured',
            'workload_type' => 'structured',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Broken range',
            'workload_type' => 'range',
            'min_weekly_minutes' => 120,
            'max_weekly_minutes' => 60,
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Broken flexible',
            'workload_type' => 'flexible',
            'weekly_target_minutes' => 60,
        ])->assertStatus(422);
    }

    public function test_programs_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $program = Program::query()->create([
            'user_id' => $other->id,
            'name' => 'Not mine',
            'workload_type' => 'flexible',
            'status' => 'active',
            'priority_tier' => 3,
        ]);

        $this->withToken($token)->getJson("/api/v1/programs/{$program->id}")->assertStatus(404);
        $this->withToken($token)->putJson("/api/v1/programs/{$program->id}", ['name' => 'Hijack'])->assertStatus(404);
        $this->withToken($token)->getJson('/api/v1/programs')->assertJsonCount(0, 'programs');
    }

    public function test_programs_can_be_listed(): void
    {
        [$user, $token] = $this->userWithToken();

        Program::query()->create([
            'user_id' => $user->id,
            'name' => 'First',
            'workload_type' => 'flexible',
            'status' => 'active',
            'priority_tier' => 3,
        ]);
        Program::query()->create([
            'user_id' => $user->id,
            'name' => 'Second',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 120,
            'status' => 'active',
            'priority_tier' => 2,
        ]);

        $this->withToken($token)->getJson('/api/v1/programs')
            ->assertStatus(200)
            ->assertJsonCount(2, 'programs');
    }

    public function test_program_can_be_updated(): void
    {
        [$user, $token] = $this->userWithToken();

        $program = Program::query()->create([
            'user_id' => $user->id,
            'name' => 'Old name',
            'workload_type' => 'flexible',
            'status' => 'active',
            'priority_tier' => 3,
        ]);

        $this->withToken($token)->putJson("/api/v1/programs/{$program->id}", [
            'name' => 'New name',
            'priority_tier' => 1,
        ])->assertStatus(200)
            ->assertJsonPath('program.name', 'New name')
            ->assertJsonPath('program.priority_tier', 1)
            ->assertJsonPath('program.workload_type', 'flexible');
    }

    public function test_program_status_lifecycle_transitions(): void
    {
        [$user, $token] = $this->userWithToken();

        $program = Program::query()->create([
            'user_id' => $user->id,
            'name' => 'Sustained stream',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 300,
            'status' => 'active',
            'priority_tier' => 3,
        ]);

        $this->withToken($token)->postJson("/api/v1/programs/{$program->id}/status", ['status' => 'paused'])
            ->assertStatus(200)
            ->assertJsonPath('program.status', 'paused');

        $this->withToken($token)->postJson("/api/v1/programs/{$program->id}/status", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('program.status', 'completed');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();

        $program = Program::query()->create([
            'user_id' => $user->id,
            'name' => 'Directly completed',
            'workload_type' => 'flexible',
            'status' => 'completed',
            'priority_tier' => 3,
        ]);

        $this->withToken($token)->postJson("/api/v1/programs/{$program->id}/status", ['status' => 'active'])
            ->assertStatus(422);
    }

    public function test_program_creation_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => '',
            'workload_type' => 'flexible',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/v1/programs', [
            'name' => 'Bad type',
            'workload_type' => 'weekly',
        ])->assertStatus(422);
    }
}
