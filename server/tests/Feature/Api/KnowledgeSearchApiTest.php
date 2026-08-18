<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/knowledge/search?q=test')->assertStatus(401);
    }

    public function test_search_requires_query_parameter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/knowledge/search')
            ->assertStatus(422);
    }

    public function test_search_requires_minimum_query_length(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=')
            ->assertStatus(422);
    }

    public function test_search_returns_empty_when_no_matches(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Meeting notes',
            'plain_text_cache' => 'Discussed project timeline',
        ]);

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=nonexistent')
            ->assertStatus(200)
            ->assertJsonPath('notes', [])
            ->assertJsonPath('query', 'nonexistent');
    }

    public function test_search_finds_notes_by_title(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Project research notes',
            'plain_text_cache' => 'Some content',
        ]);
        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Meeting notes',
            'plain_text_cache' => 'Discussed project timeline',
        ]);

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=research')
            ->assertStatus(200)
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Project research notes');
    }

    public function test_search_finds_notes_by_plain_text_content(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Daily log',
            'plain_text_cache' => 'Working on the quarterly report',
        ]);
        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Meeting notes',
            'plain_text_cache' => 'Discussed project timeline',
        ]);

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=quarterly')
            ->assertStatus(200)
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Daily log');
    }

    public function test_search_is_scoped_to_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Note::factory()->create([
            'user_id' => $owner->id,
            'title' => 'Owner private notes',
            'plain_text_cache' => 'Secret content here',
        ]);
        Note::factory()->create([
            'user_id' => $other->id,
            'title' => 'Other user notes',
            'plain_text_cache' => 'Secret content here',
        ]);

        $token = $owner->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=Secret')
            ->assertStatus(200)
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Owner private notes');
    }

    public function test_search_returns_notes_ordered_by_updated_at(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $older = Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Older note about project',
            'plain_text_cache' => 'content',
            'updated_at' => now()->subDay(),
        ]);
        $newer = Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Newer note about project',
            'plain_text_cache' => 'content',
            'updated_at' => now(),
        ]);

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=project')
            ->assertStatus(200)
            ->assertJsonPath('notes.0.title', 'Newer note about project')
            ->assertJsonPath('notes.1.title', 'Older note about project');
    }

    public function test_search_returns_query_in_response(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Note::factory()->create([
            'user_id' => $user->id,
            'title' => 'Test note',
            'plain_text_cache' => 'content',
        ]);

        $this->withToken($token)->getJson('/api/v1/knowledge/search?q=test')
            ->assertStatus(200)
            ->assertJsonPath('query', 'test');
    }
}
