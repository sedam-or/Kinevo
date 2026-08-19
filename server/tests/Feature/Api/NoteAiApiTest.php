<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NoteAiApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithNote(string $content = 'Buy milk, call dentist, write report.'): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $note = Note::query()->create([
            'user_id' => $user->id,
            'title' => 'Errands',
            'document_json' => ['type' => 'doc', 'content' => []],
            'markdown_cache' => $content,
            'plain_text_cache' => $content,
            'version' => 1,
        ]);

        return [$user, $token, $note];
    }

    private function fakeOllama(array $byPrompt): void
    {
        config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]);

        Http::fake(function ($request) use ($byPrompt) {
            $prompt = (string) data_get($request->data(), 'prompt', '');

            foreach ($byPrompt as $needle => $body) {
                if (str_contains($prompt, $needle)) {
                    return Http::response(['response' => $body], 200);
                }
            }

            return Http::response(['response' => '{}'], 200);
        });
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/ai/summarize-note', ['note_id' => 1])->assertStatus(401);
        $this->postJson('/api/v1/ai/extract-tasks', ['note_id' => 1])->assertStatus(401);
    }

    public function test_summarize_note_generates_pending_proposal(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'summarize' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'Buy milk, call dentist, and write the report.',
                'key_points' => ['buy milk', 'call dentist', 'write report'],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/summarize-note', [
            'note_id' => $note->id,
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'summary')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonPath('proposal.payload.summary', 'Buy milk, call dentist, and write the report.');

        $this->assertDatabaseHas('ai_proposals', [
            'user_id' => $user->id,
            'proposal_type' => 'summary',
            'decision' => 'pending',
        ]);
        $this->assertDatabaseHas('ai_runs', [
            'user_id' => $user->id,
            'proposal_type' => 'summary',
            'status' => 'success',
        ]);
    }

    public function test_extract_tasks_generates_pending_proposal(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'extract' => json_encode([
                'type' => 'task_extraction_proposal',
                'tasks' => [
                    ['title' => 'Buy milk', 'estimated_minutes' => 10],
                    ['title' => 'Write report', 'due_at' => '2026-08-20'],
                ],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/extract-tasks', [
            'note_id' => $note->id,
        ])->assertStatus(200)
            ->assertJsonPath('proposal.proposal_type', 'task_extraction')
            ->assertJsonPath('proposal.decision', 'pending')
            ->assertJsonCount(2, 'proposal.payload.tasks');

        // No task is created before acceptance (FR-62).
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_accept_extracted_tasks_creates_tasks_in_transaction(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'extract' => json_encode([
                'type' => 'task_extraction_proposal',
                'tasks' => [
                    ['title' => 'Buy milk', 'estimated_minutes' => 10],
                    ['title' => 'Write report', 'due_at' => '2026-08-20'],
                ],
            ]),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/ai/extract-tasks', [
            'note_id' => $note->id,
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(200)
            ->assertJsonCount(2, 'tasks');

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Buy milk',
            'estimated_minutes' => 10,
        ]);
        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title' => 'Write report',
        ]);
        $this->assertDatabaseHas('ai_proposals', [
            'id' => $proposalId,
            'decision' => 'accepted',
        ]);
    }

    public function test_reject_extraction_creates_no_tasks(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'extract' => json_encode([
                'type' => 'task_extraction_proposal',
                'tasks' => [['title' => 'Buy milk']],
            ]),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/ai/extract-tasks', [
            'note_id' => $note->id,
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $this->withToken($token)->postJson("/api/v1/ai/proposals/{$proposalId}/reject")
            ->assertStatus(200)
            ->assertJsonPath('proposal.decision', 'rejected');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_note_must_be_owned(): void
    {
        [$user, $token] = $this->userWithNote();
        $other = User::factory()->create();
        $foreign = Note::query()->create([
            'user_id' => $other->id,
            'title' => 'Foreign',
            'plain_text_cache' => 'x',
            'version' => 1,
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/summarize-note', [
            'note_id' => $foreign->id,
        ])->assertStatus(404);
    }

    public function test_summarize_rejects_invalid_output(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'summarize' => json_encode([
                'type' => 'summary_proposal',
                'summary' => 'S',
                'key_points' => [],
            ]),
        ]);

        $this->withToken($token)->postJson('/api/v1/ai/summarize-note', [
            'note_id' => $note->id,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'AI_OUTPUT_INVALID');

        $this->assertDatabaseCount('ai_proposals', 0);
    }

    public function test_accept_is_owner_scoped(): void
    {
        [$user, $token, $note] = $this->userWithNote();

        $this->fakeOllama([
            'extract' => json_encode([
                'type' => 'task_extraction_proposal',
                'tasks' => [['title' => 'Buy milk']],
            ]),
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/ai/extract-tasks', [
            'note_id' => $note->id,
        ])->assertStatus(200);
        $proposalId = $response->json('proposal.id');

        $other = User::factory()->create();
        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->postJson("/api/v1/ai/proposals/{$proposalId}/accept")
            ->assertStatus(404);
        $this->assertDatabaseCount('tasks', 0);
    }
}
