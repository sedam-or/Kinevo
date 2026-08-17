<?php

namespace Tests\Feature\Api;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithGoal(array $goal = []): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => $goal['title'] ?? 'Ship product',
            'horizon' => $goal['horizon'] ?? 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        return [$user, $token, $goal];
    }

    public function test_milestones_require_authentication(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();

        $this->getJson("/api/v1/goals/{$goal->id}/milestones")->assertStatus(401);
        $this->postJson("/api/v1/goals/{$goal->id}/milestones", [])->assertStatus(401);
    }

    public function test_milestone_can_be_created_for_goal(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones", [
            'title' => 'Research phase',
            'description' => 'User interviews and competitive scan',
            'target_date' => '2026-09-01',
            'estimated_minutes' => 600,
        ])->assertStatus(201)
            ->assertJsonPath('milestone.goal_id', $goal->id)
            ->assertJsonPath('milestone.title', 'Research phase')
            ->assertJsonPath('milestone.sequence', 1)
            ->assertJsonPath('milestone.status', 'planned')
            ->assertJsonPath('milestone.progress', 0);

        $this->assertDatabaseHas('milestones', [
            'goal_id' => $goal->id,
            'user_id' => $user->id,
            'title' => 'Research phase',
            'sequence' => 1,
        ]);
    }

    public function test_milestone_creation_requires_owned_goal(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $other = User::factory()->create();
        $foreign = Goal::query()->create([
            'user_id' => $other->id,
            'title' => 'Not mine',
            'horizon' => 'custom',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$foreign->id}/milestones", [
            'title' => 'Hijack',
        ])->assertStatus(404);
    }

    public function test_milestones_are_scoped_to_owner(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $other = User::factory()->create();
        $foreign = Milestone::query()->create([
            'user_id' => $other->id,
            'goal_id' => $goal->id,
            'title' => 'Not mine',
            'sequence' => 1,
            'status' => 'planned',
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->getJson("/api/v1/goals/{$goal->id}/milestones/{$foreign->id}")
            ->assertStatus(404);
        $this->withToken($token)->putJson("/api/v1/goals/{$goal->id}/milestones/{$foreign->id}", ['title' => 'Hijack'])
            ->assertStatus(404);
    }

    public function test_milestones_list_ordered_by_sequence(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();

        foreach (['Zeta' => 2, 'Alpha' => 0, 'Beta' => 1] as $title => $sequence) {
            Milestone::query()->create([
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'title' => $title,
                'sequence' => $sequence,
                'status' => 'planned',
                'progress_mode' => 'derived',
                'progress' => 0,
            ]);
        }

        $response = $this->withToken($token)->getJson("/api/v1/goals/{$goal->id}/milestones")
            ->assertStatus(200)
            ->assertJsonCount(3, 'milestones');

        $this->assertSame(['Alpha', 'Beta', 'Zeta'], array_column($response->json('milestones'), 'title'));
    }

    public function test_milestone_can_be_updated(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $milestone = Milestone::query()->create([
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'title' => 'Old title',
            'sequence' => 1,
            'status' => 'planned',
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->putJson("/api/v1/goals/{$goal->id}/milestones/{$milestone->id}", [
            'title' => 'New title',
            'estimated_minutes' => 90,
        ])->assertStatus(200)
            ->assertJsonPath('milestone.title', 'New title')
            ->assertJsonPath('milestone.estimated_minutes', 90)
            ->assertJsonPath('milestone.sequence', 1);
    }

    public function test_milestone_status_lifecycle_transitions(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $milestone = Milestone::query()->create([
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'title' => 'Build beta',
            'sequence' => 1,
            'status' => 'planned',
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones/{$milestone->id}/status", ['status' => 'active'])
            ->assertStatus(200)
            ->assertJsonPath('milestone.status', 'active');

        $response = $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones/{$milestone->id}/status", ['status' => 'completed'])
            ->assertStatus(200)
            ->assertJsonPath('milestone.status', 'completed');

        $this->assertNotNull($response->json('milestone.completed_at'));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $milestone = Milestone::query()->create([
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'title' => 'Complete directly',
            'sequence' => 1,
            'status' => 'completed',
            'progress_mode' => 'derived',
            'progress' => 100,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones/{$milestone->id}/status", ['status' => 'active'])
            ->assertStatus(422);
    }

    public function test_milestones_can_be_reordered(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $ids = [];
        foreach (['First', 'Second', 'Third'] as $title) {
            $ids[] = Milestone::query()->create([
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'title' => $title,
                'sequence' => count($ids) + 1,
                'status' => 'planned',
                'progress_mode' => 'derived',
                'progress' => 0,
            ])->id;
        }

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones/reorder", [
            'ordered_ids' => [$ids[2], $ids[0], $ids[1]],
        ])->assertStatus(200);

        $this->assertDatabaseHas('milestones', ['id' => $ids[2], 'sequence' => 0]);
        $this->assertDatabaseHas('milestones', ['id' => $ids[0], 'sequence' => 1]);
        $this->assertDatabaseHas('milestones', ['id' => $ids[1], 'sequence' => 2]);
    }

    public function test_reorder_rejects_foreign_milestone(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();
        $other = User::factory()->create();
        $foreign = Milestone::query()->create([
            'user_id' => $other->id,
            'goal_id' => $goal->id,
            'title' => 'Foreign',
            'sequence' => 1,
            'status' => 'planned',
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones/reorder", [
            'ordered_ids' => [$foreign->id],
        ])->assertStatus(422);
    }

    public function test_milestone_creation_validates_input(): void
    {
        [$user, $token, $goal] = $this->userWithGoal();

        $this->withToken($token)->postJson("/api/v1/goals/{$goal->id}/milestones", ['title' => ''])
            ->assertStatus(422);
    }
}
