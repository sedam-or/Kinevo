<?php

namespace Tests\Feature\Api;

use App\Models\Canvas;
use App\Models\CanvasDocument;
use App\Models\CanvasFile;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_canvases_require_authentication(): void
    {
        $this->getJson('/api/v1/canvases')->assertStatus(401);
        $this->postJson('/api/v1/canvases', [])->assertStatus(401);
    }

    public function test_canvas_can_be_created(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/canvases', [
            'title' => 'Research board',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('canvas.title', 'Research board')
            ->assertJsonPath('canvas.version', 1);

        $this->assertDatabaseHas('canvases', [
            'user_id' => $user->id,
            'title' => 'Research board',
            'version' => 1,
        ]);
    }

    public function test_canvas_creation_validates_title(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/canvases', [
            'title' => '',
        ])->assertStatus(422);
    }

    public function test_canvas_can_be_created_with_context(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $goal = Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Research goal',
            'horizon' => 'custom',
            'status' => 'draft',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);

        $response = $this->withToken($token)->postJson('/api/v1/canvases', [
            'title' => 'Goal canvas',
            'goal_id' => $goal->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('canvas.goal_id', $goal->id);
    }

    public function test_canvases_can_be_listed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Canvas::factory()->create(['user_id' => $user->id, 'title' => 'Board A']);
        Canvas::factory()->create(['user_id' => $user->id, 'title' => 'Board B']);

        $this->withToken($token)->getJson('/api/v1/canvases')
            ->assertStatus(200)
            ->assertJsonCount(2, 'canvases');
    }

    public function test_canvas_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $canvas = Canvas::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/canvases/{$canvas->id}")->assertStatus(404);
    }

    public function test_canvas_can_be_shown_with_document(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id, 'title' => 'My canvas']);
        $document = CanvasDocument::factory()->create(['canvas_id' => $canvas->id]);

        $this->withToken($token)->getJson("/api/v1/canvases/{$canvas->id}")
            ->assertStatus(200)
            ->assertJsonPath('canvas.title', 'My canvas')
            ->assertJsonPath('document.version', 1)
            ->assertJsonPath('document.schema_version', 1);
    }

    public function test_canvas_without_document_has_null_document(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->getJson("/api/v1/canvases/{$canvas->id}")
            ->assertStatus(200)
            ->assertJsonPath('document', null);
    }

    public function test_canvas_scene_can_be_saved(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $scene = ['type' => 'excalidraw', 'elements' => [['id' => 'rect1']], 'appState' => []];

        $this->withToken($token)->putJson("/api/v1/canvases/{$canvas->id}", [
            'scene_json' => $scene,
            'base_version' => 0,
        ])
            ->assertStatus(200)
            ->assertJsonPath('document.version', 1)
            ->assertJsonPath('document.scene_json.elements.0.id', 'rect1');
    }

    public function test_canvas_scene_update_bumps_version(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);
        CanvasDocument::factory()->create(['canvas_id' => $canvas->id, 'version' => 1]);

        $scene = ['type' => 'excalidraw', 'elements' => [], 'appState' => []];

        $this->withToken($token)->putJson("/api/v1/canvases/{$canvas->id}", [
            'scene_json' => $scene,
            'base_version' => 1,
        ])
            ->assertStatus(200)
            ->assertJsonPath('document.version', 2);
    }

    public function test_stale_canvas_save_returns_409(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);
        CanvasDocument::factory()->create(['canvas_id' => $canvas->id, 'version' => 5]);

        $scene = ['type' => 'excalidraw', 'elements' => [], 'appState' => []];

        $this->withToken($token)->putJson("/api/v1/canvases/{$canvas->id}", [
            'scene_json' => $scene,
            'base_version' => 1,
        ])
            ->assertStatus(409);
    }

    public function test_canvas_save_validates_input(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->putJson("/api/v1/canvases/{$canvas->id}", [
            'base_version' => 0,
        ])->assertStatus(422);
    }

    public function test_save_other_users_canvas_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $canvas = Canvas::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->putJson("/api/v1/canvases/{$canvas->id}", [
            'scene_json' => ['elements' => []],
            'base_version' => 0,
        ])->assertStatus(404);
    }

    public function test_canvas_can_be_renamed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id, 'title' => 'Old title']);

        $this->withToken($token)->patchJson("/api/v1/canvases/{$canvas->id}", [
            'title' => 'New title',
        ])
            ->assertStatus(200)
            ->assertJsonPath('canvas.title', 'New title');

        $this->assertDatabaseHas('canvases', [
            'id' => $canvas->id,
            'title' => 'New title',
        ]);
    }

    public function test_canvas_rename_validates_title(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->patchJson("/api/v1/canvases/{$canvas->id}", [
            'title' => '',
        ])->assertStatus(422);
    }

    public function test_canvas_rename_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $canvas = Canvas::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->patchJson("/api/v1/canvases/{$canvas->id}", [
            'title' => 'Nope',
        ])->assertStatus(404);
    }

    public function test_canvas_can_be_archived(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->postJson("/api/v1/canvases/{$canvas->id}/archive")
            ->assertStatus(200)
            ->assertJsonPath('canvas.id', $canvas->id);

        $this->assertNotNull($canvas->fresh()->archived_at);
    }

    public function test_archived_canvas_is_hidden_from_list(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        Canvas::factory()->create(['user_id' => $user->id, 'title' => 'Active board']);
        $archived = Canvas::factory()->create(['user_id' => $user->id, 'title' => 'Old board']);

        $this->withToken($token)->postJson("/api/v1/canvases/{$archived->id}/archive")
            ->assertStatus(200);

        $this->withToken($token)->getJson('/api/v1/canvases')
            ->assertStatus(200)
            ->assertJsonCount(1, 'canvases')
            ->assertJsonPath('canvases.0.title', 'Active board');
    }

    public function test_canvas_archive_is_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $canvas = Canvas::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->postJson("/api/v1/canvases/{$canvas->id}/archive")
            ->assertStatus(404);
    }

    public function test_canvas_file_can_be_added(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $response = $this->withToken($token)->postJson("/api/v1/canvases/{$canvas->id}/files", [
            'storage_path' => 'canvases/abc.png',
            'content_type' => 'image/png',
            'size_bytes' => 2048,
            'sha256' => str_repeat('a', 64),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('file.canvas_id', $canvas->id)
            ->assertJsonPath('file.storage_path', 'canvases/abc.png')
            ->assertJsonPath('file.content_type', 'image/png')
            ->assertJsonPath('file.size_bytes', 2048);

        $this->assertDatabaseHas('canvas_files', [
            'canvas_id' => $canvas->id,
            'storage_path' => 'canvases/abc.png',
            'content_type' => 'image/png',
            'size_bytes' => 2048,
        ]);
    }

    public function test_canvas_file_creation_validates_input(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->postJson("/api/v1/canvases/{$canvas->id}/files", [
            'storage_path' => '',
            'content_type' => '',
            'size_bytes' => -1,
        ])->assertStatus(422);
    }

    public function test_canvas_files_can_be_listed(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        $canvas = Canvas::factory()->create(['user_id' => $user->id]);
        CanvasFile::factory()->create(['canvas_id' => $canvas->id, 'content_type' => 'image/png']);
        CanvasFile::factory()->create(['canvas_id' => $canvas->id, 'content_type' => 'image/svg+xml']);

        $this->withToken($token)->getJson("/api/v1/canvases/{$canvas->id}/files")
            ->assertStatus(200)
            ->assertJsonCount(2, 'files');
    }

    public function test_canvas_files_are_scoped_to_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $canvas = Canvas::factory()->create(['user_id' => $owner->id]);

        $token = $other->createToken('other')->plainTextToken;

        $this->withToken($token)->getJson("/api/v1/canvases/{$canvas->id}/files")->assertStatus(404);
        $this->withToken($token)->postJson("/api/v1/canvases/{$canvas->id}/files", [
            'storage_path' => 'path',
            'content_type' => 'image/png',
            'size_bytes' => 10,
        ])->assertStatus(404);
    }
}
