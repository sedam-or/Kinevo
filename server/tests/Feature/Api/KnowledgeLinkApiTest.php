<?php

namespace Tests\Feature\Api;

use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Note;
use App\Models\Program;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createNote(int $userId): Note
    {
        return Note::query()->create([
            'user_id' => $userId,
            'title' => 'Research on goals',
            'document_json' => null,
            'version' => 1,
        ]);
    }

    private function createGoal(int $userId): Goal
    {
        return Goal::query()->create([
            'user_id' => $userId,
            'title' => 'Learn Laravel',
            'horizon' => 'yearly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    public function test_links_require_authentication(): void
    {
        $this->getJson('/api/v1/notes/1/links')->assertStatus(401);
        $this->postJson('/api/v1/notes/1/links', [])->assertStatus(401);
        $this->deleteJson('/api/v1/notes/1/links/1')->assertStatus(401);
        $this->getJson('/api/v1/knowledge/links?target_type=goal&target_id=1')->assertStatus(401);
    }

    public function test_note_link_can_be_created_to_goal(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(201)
            ->assertJsonPath('link.source_type', 'note')
            ->assertJsonPath('link.source_id', $note->id)
            ->assertJsonPath('link.target_type', 'goal')
            ->assertJsonPath('link.target_id', $goal->id)
            ->assertJsonPath('link.link_type', 'supports')
            ->assertJsonPath('link.user_id', $user->id);

        $this->assertDatabaseHas('knowledge_links', [
            'user_id' => $user->id,
            'source_type' => 'note',
            'source_id' => $note->id,
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ]);
    }

    public function test_link_can_target_milestone_program_and_task(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);

        $goal = $this->createGoal($user->id);
        $milestone = Milestone::query()->create([
            'user_id' => $user->id,
            'goal_id' => $goal->id,
            'title' => 'Milestone one',
            'sequence' => 1,
            'status' => 'planned',
            'progress' => 0,
        ]);
        $program = Program::query()->create([
            'user_id' => $user->id,
            'name' => 'Writing habit',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 120,
            'status' => 'active',
            'priority_tier' => 3,
        ]);
        $task = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Task one',
            'status' => 'backlog',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'milestone',
            'target_id' => $milestone->id,
            'link_type' => 'references',
        ])->assertStatus(201)->assertJsonPath('link.target_type', 'milestone');

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'program',
            'target_id' => $program->id,
            'link_type' => 'related_to',
        ])->assertStatus(201)->assertJsonPath('link.target_type', 'program');

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'task',
            'target_id' => $task->id,
            'link_type' => 'evidence_for',
        ])->assertStatus(201)->assertJsonPath('link.target_type', 'task');
    }

    public function test_link_creation_requires_owned_note(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $note = $this->createNote($other->id);
        $goal = $this->createGoal($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(404);
    }

    public function test_link_creation_requires_owned_target(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $other = User::factory()->create();
        $goal = $this->createGoal($other->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(404);
    }

    public function test_link_creation_rejects_unknown_target(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => 999999,
            'link_type' => 'supports',
        ])->assertStatus(404);
    }

    public function test_link_creation_validates_payload(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'canvas',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(422);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'arbitrary',
        ])->assertStatus(422);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'link_type' => 'supports',
        ])->assertStatus(422);
    }

    public function test_duplicate_link_is_conflict(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $payload = [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ];

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", $payload)
            ->assertStatus(201);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", $payload)
            ->assertStatus(409);
    }

    public function test_note_links_can_be_listed(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(201);

        $this->withToken($token)->getJson("/api/v1/notes/{$note->id}/links")
            ->assertStatus(200)
            ->assertJsonCount(1, 'links')
            ->assertJsonPath('links.0.target_type', 'goal')
            ->assertJsonPath('links.0.link_type', 'supports');
    }

    public function test_note_links_list_requires_owned_note(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $note = $this->createNote($other->id);

        $this->withToken($token)->getJson("/api/v1/notes/{$note->id}/links")->assertStatus(404);
    }

    public function test_reverse_navigation_lists_links_by_target(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $otherNote = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(201);

        $this->withToken($token)->postJson("/api/v1/notes/{$otherNote->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'evidence_for',
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/knowledge/links?target_type=goal&target_id='.$goal->id)
            ->assertStatus(200)
            ->assertJsonCount(2, 'links')
            ->assertJsonPath('links.0.source_id', $note->id)
            ->assertJsonPath('links.1.source_id', $otherNote->id);
    }

    public function test_reverse_navigation_requires_owned_target(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $goal = $this->createGoal($other->id);

        $this->withToken($token)->getJson('/api/v1/knowledge/links?target_type=goal&target_id='.$goal->id)
            ->assertStatus(404);
    }

    public function test_link_can_be_deleted(): void
    {
        [$user, $token] = $this->userWithToken();
        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);

        $link = $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(201)->json('link');

        $this->withToken($token)->deleteJson("/api/v1/notes/{$note->id}/links/{$link['id']}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('knowledge_links', ['id' => $link['id']]);
    }

    public function test_delete_requires_owned_note_and_link(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $note = $this->createNote($user->id);
        $goal = $this->createGoal($user->id);
        $link = $this->withToken($token)->postJson("/api/v1/notes/{$note->id}/links", [
            'target_type' => 'goal',
            'target_id' => $goal->id,
            'link_type' => 'supports',
        ])->assertStatus(201)->json('link');

        $this->withToken($token)->deleteJson("/api/v1/notes/{$other->id}/links/{$link['id']}")
            ->assertStatus(404);

        $foreignNote = $this->createNote($other->id);
        $this->withToken($token)->deleteJson("/api/v1/notes/{$foreignNote->id}/links/{$link['id']}")
            ->assertStatus(404);

        $this->withToken($token)->deleteJson("/api/v1/notes/{$note->id}/links/999999")
            ->assertStatus(404);

        $this->assertDatabaseHas('knowledge_links', ['id' => $link['id']]);
    }
}
