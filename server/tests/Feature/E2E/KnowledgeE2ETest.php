<?php

namespace Tests\Feature\E2E;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-154 — Knowledge E2E.
 *
 * Walks the knowledge/note lifecycle in order:
 *   create Note → edit Note → save → search → link Goal → link Milestone →
 *   link Program → link Task → create Canvas → link Canvas.
 *
 * Every assertion targets user-visible API responses — the payloads the UI
 * renders from. Database-only assertions are deliberately not used.
 */
final class KnowledgeE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_lifecycle_journey(): void
    {
        $owner = User::factory()->create([
            'email' => 'knower@example.com',
            'password' => bcrypt('password123'),
        ]);
        $api = $this->withToken($owner->createToken('device')->plainTextToken);

        // ── Create Note ─────────────────────────────────────────────────
        $note = $api->postJson('/api/v1/notes', [
            'title' => 'Knowledge research note',
            'document_json' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ideas']]]],
            ],
            'markdown_cache' => '# Knowledge research note',
            'plain_text_cache' => 'Research findings about goals and milestones',
        ])->assertStatus(201)
            ->json('note');

        $noteId = $note['id'];
        $this->assertSame('Knowledge research note', $note['title']);
        $this->assertSame(1, $note['version']);
        $baseVersion = $note['version'];

        // ── Edit Note (modify the document body) ────────────────────────
        $edited = $api->patchJson("/api/v1/notes/{$noteId}", [
            'title' => 'Knowledge research note (revised)',
            'document_json' => [
                'type' => 'doc',
                'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Revised ideas']]]],
            ],
            'base_version' => $baseVersion,
        ])->assertStatus(200)
            ->json('note');

        $this->assertSame('Knowledge research note (revised)', $edited['title']);
        $this->assertGreaterThan($baseVersion, $edited['version']);

        // ── Save (persist derived caches / autosave) ─────────────────────
        $saveBase = $edited['version'];
        $saved = $api->patchJson("/api/v1/notes/{$noteId}", [
            'markdown_cache' => '# Knowledge research note (revised)',
            'plain_text_cache' => 'Revised research findings about goals and milestones',
            'base_version' => $saveBase,
        ])->assertStatus(200)
            ->json('note');

        $this->assertGreaterThan($saveBase, $saved['version']);
        $this->assertSame('Revised research findings about goals and milestones', $saved['plain_text_cache']);

        // ── Reload confirms the edited title and saved cache persisted ───
        $api->getJson("/api/v1/notes/{$noteId}")
            ->assertStatus(200)
            ->assertJsonPath('note.title', 'Knowledge research note (revised)')
            ->assertJsonPath('note.plain_text_cache', 'Revised research findings about goals and milestones');

        // ── Search ───────────────────────────────────────────────────────
        $api->getJson('/api/v1/knowledge/search?q=research')
            ->assertStatus(200)
            ->assertJsonPath('query', 'research')
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.id', $noteId);

        // ── Link Goal ────────────────────────────────────────────────────
        $goal = $api->postJson('/api/v1/goals', [
            'title' => 'Learn knowledge graph',
            'horizon' => 'quarterly',
            'target_date' => '2026-12-31',
        ])->assertStatus(201)
            ->json('goal');

        $api->postJson("/api/v1/notes/{$noteId}/links", [
            'target_type' => 'goal',
            'target_id' => $goal['id'],
            'link_type' => 'supports',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'goal')
            ->assertJsonPath('link.target_id', $goal['id']);

        // ── Link Milestone ───────────────────────────────────────────────
        $milestone = $api->postJson("/api/v1/goals/{$goal['id']}/milestones", [
            'title' => 'Draft knowledge model',
        ])->assertStatus(201)
            ->json('milestone');

        $api->postJson("/api/v1/notes/{$noteId}/links", [
            'target_type' => 'milestone',
            'target_id' => $milestone['id'],
            'link_type' => 'references',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'milestone');

        // ── Link Program ─────────────────────────────────────────────────
        $program = $api->postJson('/api/v1/programs', [
            'name' => 'Research program',
            'workload_type' => 'structured',
            'weekly_target_minutes' => 120,
        ])->assertStatus(201)
            ->json('program');

        $api->postJson("/api/v1/notes/{$noteId}/links", [
            'target_type' => 'program',
            'target_id' => $program['id'],
            'link_type' => 'related_to',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'program');

        // ── Link Task ────────────────────────────────────────────────────
        $task = $api->postJson('/api/v1/tasks', [
            'title' => 'Write knowledge summary',
            'priority_tier' => 2,
            'goal_id' => $goal['id'],
        ])->assertStatus(201)
            ->json('task');

        $api->postJson("/api/v1/notes/{$noteId}/links", [
            'target_type' => 'task',
            'target_id' => $task['id'],
            'link_type' => 'evidence_for',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'task');

        // ── Create Canvas ────────────────────────────────────────────────
        $canvas = $api->postJson('/api/v1/canvases', [
            'title' => 'Knowledge map',
        ])->assertStatus(201)
            ->json('canvas');

        // ── Link Canvas ──────────────────────────────────────────────────
        $api->postJson("/api/v1/notes/{$noteId}/links", [
            'target_type' => 'canvas',
            'target_id' => $canvas['id'],
            'link_type' => 'related_to',
        ])->assertStatus(201)
            ->assertJsonPath('link.target_type', 'canvas')
            ->assertJsonPath('link.target_id', $canvas['id']);

        // ── Final state verification ─────────────────────────────────────
        $links = $api->getJson("/api/v1/notes/{$noteId}/links")->json();

        $this->assertCount(5, $links['links']);

        $types = collect($links['links'])->pluck('target_type')->sort()->values()->all();
        $this->assertSame(['canvas', 'goal', 'milestone', 'program', 'task'], $types);
    }
}
