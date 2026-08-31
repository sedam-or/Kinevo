<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-016 §2.10 — Sacred Anchor producer (scheduling slice of FR-04):
 * task-level flag with at-most-one-per-user validation, consumed by the
 * draft generator (anchor placed first, 25 min, at/after 06:00).
 */
final class SacredAnchorApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, array $overrides = []): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $overrides['title'] ?? 'Task',
            'status' => 'backlog',
            'priority_tier' => 3,
            'estimated_minutes' => $overrides['estimated_minutes'] ?? 60,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
            'is_sacred_anchor' => $overrides['is_sacred_anchor'] ?? false,
        ]);
    }

    public function test_task_can_be_marked_as_sacred_anchor(): void
    {
        [$user, $token] = $this->userWithToken();

        $response = $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Morning study',
            'is_sacred_anchor' => true,
        ]);

        $response->assertStatus(201)->assertJsonPath('task.is_sacred_anchor', true);
    }

    public function test_second_sacred_anchor_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id, ['is_sacred_anchor' => true]);

        $this->withToken($token)->postJson('/api/v1/tasks', [
            'title' => 'Second anchor',
            'is_sacred_anchor' => true,
        ])->assertStatus(422);
    }

    public function test_reassigning_the_anchor_is_rejected(): void
    {
        [$user, $token] = $this->userWithToken();
        $anchor = $this->createTask($user->id, ['is_sacred_anchor' => true]);
        $other = $this->createTask($user->id, ['title' => 'Other task']);

        $this->withToken($token)->putJson("/api/v1/tasks/{$other->id}", [
            'is_sacred_anchor' => true,
        ])->assertStatus(422);

        // Clearing the anchor remains possible.
        $this->withToken($token)->putJson("/api/v1/tasks/{$anchor->id}", [
            'is_sacred_anchor' => false,
        ])->assertStatus(200)->assertJsonPath('task.is_sacred_anchor', false);

        // And after clearing, another task may take it.
        $this->withToken($token)->putJson("/api/v1/tasks/{$other->id}", [
            'is_sacred_anchor' => true,
        ])->assertStatus(200);
    }

    public function test_draft_places_the_sacred_anchor(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id, ['title' => 'Morning study', 'is_sacred_anchor' => true, 'estimated_minutes' => 25]);
        $this->createTask($user->id, ['title' => 'Regular work', 'estimated_minutes' => 60]);

        // Tight day: a block occupying the morning forces the anchor into the
        // first qualifying slot at/after 06:00 on a later day in the horizon.
        $this->withToken($token)->postJson('/api/v1/hard-landscape', [
            'title' => 'Full day',
            'type' => 'one_time',
            'start_at' => now()->toDateString().' 06:00',
            'end_at' => now()->toDateString().' 23:59',
        ])->assertStatus(201);

        $from = now()->toDateString();
        $to = now()->addDays(3)->toDateString();

        $response = $this->withToken($token)->postJson('/api/v1/schedule/draft', [
            'from' => $from,
            'to' => $to,
        ]);

        $response->assertStatus(200);
        $assignments = collect($response->json('draft.assignments'));
        $anchor = $assignments->firstWhere('task_id', (string) Task::query()->where('user_id', $user->id)->where('is_sacred_anchor', true)->value('id'));
        $this->assertNotNull($anchor, 'the sacred anchor is placed first in the draft');
        $this->assertGreaterThanOrEqual(6, (int) substr((string) $anchor['start'], 11, 2), 'anchor sits at/after 06:00 (ANCHOR_EARLIEST_HOUR)');
    }
}
