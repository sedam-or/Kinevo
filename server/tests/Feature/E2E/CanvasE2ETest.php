<?php

namespace Tests\Feature\E2E;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-153 — Canvas E2E.
 *
 * Walks the canvas lifecycle in order:
 *   open → draw → autosave → reload → offline edit → reconnect → sync →
 *   version conflict → read-only → linked Goal → linked Task.
 *
 * Every assertion is made against user-visible API responses — the payloads
 * the UI renders from. Database-only assertions are deliberately not used.
 */
final class CanvasE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_canvas_lifecycle_journey(): void
    {
        $owner = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => bcrypt('password123'),
        ]);

        $deviceA = $this->withToken($owner->createToken('device-a')->plainTextToken);
        $deviceB = $this->withToken($owner->createToken('device-b')->plainTextToken);

        // ── Open canvas ──────────────────────────────────────────────────
        $canvas = $deviceA->postJson('/api/v1/canvases', [
            'title' => 'Design canvas',
        ])->assertStatus(201)
            ->json('canvas');

        $canvasId = $canvas['id'];

        $deviceA->getJson("/api/v1/canvases/{$canvasId}")
            ->assertStatus(200)
            ->assertJsonPath('canvas.title', 'Design canvas')
            ->assertJsonPath('canvas.version', 1)
            ->assertJsonPath('document', null);

        // ── Draw + autosave ──────────────────────────────────────────────
        $sceneV1 = [
            'type' => 'excalidraw',
            'elements' => [
                ['id' => 'rect1', 'type' => 'rect', 'x' => 10, 'y' => 20],
                ['id' => 'text1', 'type' => 'text', 'x' => 100, 'y' => 200],
            ],
            'appState' => ['gridSize' => 10],
        ];

        $saveV1 = $deviceA->putJson("/api/v1/canvases/{$canvasId}", [
            'scene_json' => $sceneV1,
            'base_version' => 0,
        ])->assertStatus(200);

        $this->assertSame(1, $saveV1->json('document.version'));
        $this->assertSame('rect1', $saveV1->json('document.scene_json.elements.0.id'));

        $sceneV2 = [
            'type' => 'excalidraw',
            'elements' => [
                ['id' => 'rect1', 'type' => 'rect', 'x' => 10, 'y' => 20],
                ['id' => 'rect2', 'type' => 'rect', 'x' => 50, 'y' => 60],
            ],
            'appState' => ['gridSize' => 10],
        ];

        $saveV2 = $deviceA->putJson("/api/v1/canvases/{$canvasId}", [
            'scene_json' => $sceneV2,
            'base_version' => 1,
        ])->assertStatus(200);

        $this->assertSame(2, $saveV2->json('document.version'));

        // ── Reload ───────────────────────────────────────────────────────
        $reloaded = $deviceA->getJson("/api/v1/canvases/{$canvasId}")->json();

        $this->assertSame(2, $reloaded['document']['version']);
        $this->assertSame('excalidraw', $reloaded['document']['scene_json']['type']);
        $this->assertCount(2, $reloaded['document']['scene_json']['elements']);
        $this->assertSame('rect2', $reloaded['document']['scene_json']['elements'][1]['id']);

        // ── Offline edit (device B edits while A is away) ────────────────
        $sceneFromB = [
            'type' => 'excalidraw',
            'elements' => [
                ['id' => 'ellipse1', 'type' => 'ellipse', 'x' => 200, 'y' => 100],
            ],
            'appState' => ['gridSize' => 10],
        ];

        $deviceB->putJson("/api/v1/canvases/{$canvasId}", [
            'scene_json' => $sceneFromB,
            'base_version' => 2,
        ])->assertStatus(200)
            ->assertJsonPath('document.version', 3);

        // ── Reconnect ────────────────────────────────────────────────────
        $fresh = $deviceA->getJson("/api/v1/canvases/{$canvasId}")->json();

        $this->assertSame(3, $fresh['document']['version']);
        $this->assertSame('ellipse1', $fresh['document']['scene_json']['elements'][0]['id']);

        // ── Sync (device A merges and saves with fresh version) ──────────
        $mergedScene = [
            'type' => 'excalidraw',
            'elements' => [
                ['id' => 'rect1', 'type' => 'rect', 'x' => 10, 'y' => 20],
                ['id' => 'rect2', 'type' => 'rect', 'x' => 50, 'y' => 60],
                ['id' => 'ellipse1', 'type' => 'ellipse', 'x' => 200, 'y' => 100],
            ],
            'appState' => ['gridSize' => 10],
        ];

        $sync = $deviceA->putJson("/api/v1/canvases/{$canvasId}", [
            'scene_json' => $mergedScene,
            'base_version' => 3,
        ])->assertStatus(200);

        $this->assertSame(4, $sync->json('document.version'));
        $this->assertCount(3, $sync->json('document.scene_json.elements'));

        // ── Version conflict (stale base_version) ────────────────────────
        $error = $deviceA->putJson("/api/v1/canvases/{$canvasId}", [
            'scene_json' => ['elements' => []],
            'base_version' => 3,
        ])->assertStatus(409)
            ->json('error');

        $this->assertStringContainsString('Canvas version conflict', $error);

        // ── Read-only (archive) ──────────────────────────────────────────
        $deviceA->postJson("/api/v1/canvases/{$canvasId}/archive")
            ->assertStatus(200)
            ->assertJsonPath('canvas.id', $canvasId)
            ->assertJsonPath('canvas.archived_at', fn ($v) => $v !== null);

        $deviceA->getJson('/api/v1/canvases')
            ->assertStatus(200)
            ->assertJsonCount(0, 'canvases');

        $deviceA->getJson("/api/v1/canvases/{$canvasId}")
            ->assertStatus(200)
            ->assertJsonPath('canvas.archived_at', fn ($v) => $v !== null);

        // ── Linked Goal ──────────────────────────────────────────────────
        $goal = $deviceA->postJson('/api/v1/goals', [
            'title' => 'Design canvas',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $deviceA->postJson("/api/v1/canvases/{$canvasId}/links", [
            'target_type' => 'goal',
            'target_id' => $goal['id'],
            'link_type' => 'supports',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'goal')
            ->assertJsonPath('link.target_id', $goal['id']);

        // ── Linked Task ──────────────────────────────────────────────────
        $task = $deviceA->postJson('/api/v1/tasks', [
            'title' => 'Canvas review',
            'priority_tier' => 2,
            'goal_id' => $goal['id'],
        ])->assertStatus(201)
            ->json('task');

        $deviceA->postJson("/api/v1/canvases/{$canvasId}/links", [
            'target_type' => 'task',
            'target_id' => $task['id'],
            'link_type' => 'references',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'task')
            ->assertJsonPath('link.target_id', $task['id']);

        // ── Final state verification ─────────────────────────────────────
        $links = $deviceA->getJson("/api/v1/canvases/{$canvasId}/links")->json();

        $this->assertCount(2, $links['links']);

        $types = collect($links['links'])->pluck('target_type')->sort()->values()->all();
        $this->assertSame(['goal', 'task'], $types);
    }
}
