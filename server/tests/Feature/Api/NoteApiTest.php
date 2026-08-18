<?php

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_notes_require_authentication(): void
    {
        $this->getJson('/api/v1/notes')->assertStatus(401);
        $this->postJson('/api/v1/notes', [])->assertStatus(401);
    }

    public function test_note_can_be_created(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/notes', [
            'title' => 'Research notes',
            'document_json' => ['type' => 'doc', 'content' => []],
            'markdown_cache' => '# Research notes',
            'plain_text_cache' => 'Research notes',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('note.title', 'Research notes')
            ->assertJsonPath('note.version', 1)
            ->assertJsonPath('note.document_json.type', 'doc');

        $this->assertDatabaseHas('notes', [
            'user_id' => $user->id,
            'title' => 'Research notes',
            'version' => 1,
        ]);
    }

    public function test_note_creation_validates_title(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/notes', [
            'title' => '',
        ])->assertStatus(422);
    }

    public function test_notes_can_be_listed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Note::factory()->create(['user_id' => $user->id, 'title' => 'Note A']);
        Note::factory()->create(['user_id' => $user->id, 'title' => 'Note B']);

        $this->withToken($token)->getJson('/api/v1/notes')
            ->assertStatus(200)
            ->assertJsonCount(2, 'notes');
    }

    public function test_note_can_be_shown(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $note = Note::factory()->create(['user_id' => $user->id, 'title' => 'My note']);

        $this->withToken($token)->getJson("/api/v1/notes/{$note->id}")
            ->assertStatus(200)
            ->assertJsonPath('note.title', 'My note');
    }

    public function test_note_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/notes/{$note->id}")->assertStatus(404);
    }

    public function test_note_can_be_updated_with_version(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $note = Note::factory()->create(['user_id' => $user->id, 'title' => 'Original', 'version' => 1]);

        $this->withToken($token)->patchJson("/api/v1/notes/{$note->id}", [
            'title' => 'Updated',
            'base_version' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('note.title', 'Updated')
            ->assertJsonPath('note.version', 2);
    }

    public function test_stale_update_returns_409(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $note = Note::factory()->create(['user_id' => $user->id, 'title' => 'Original', 'version' => 3]);

        $this->withToken($token)->patchJson("/api/v1/notes/{$note->id}", [
            'title' => 'Stale update',
            'base_version' => 1,
        ])->assertStatus(409);
    }

    public function test_update_missing_base_version_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $note = Note::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->patchJson("/api/v1/notes/{$note->id}", [
            'title' => 'No version',
        ])->assertStatus(422);
    }

    public function test_update_other_users_note_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $owner->id, 'version' => 1]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/notes/{$note->id}", [
            'title' => 'Hijacked',
            'base_version' => 1,
        ])->assertStatus(404);
    }

    public function test_notes_list_is_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Note::factory()->create(['user_id' => $user->id, 'title' => 'Mine']);
        Note::factory()->create(['user_id' => $other->id, 'title' => 'Theirs']);

        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/notes')
            ->assertStatus(200)
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Mine');
    }
}
