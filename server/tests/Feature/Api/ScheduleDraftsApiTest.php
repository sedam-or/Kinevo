<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR-016 §2.5 — persisted (weekly) draft lifecycle over the API: review,
 * apply (closes the draft + acknowledges review), discard, staleness.
 */
final class ScheduleDraftsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, string $title = 'Flexible work'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'backlog',
            'priority_tier' => 2,
            'estimated_minutes' => 60,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    private function prepareWeekly(int $userId): void
    {
        Artisan::call('schedule:prepare-weekly', ['--user' => $userId]);
    }

    public function test_pending_weekly_draft_is_listed(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id);
        $this->prepareWeekly($user->id);

        $response = $this->withToken($token)->getJson('/api/v1/schedule/drafts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'drafts')
            ->assertJsonPath('drafts.0.source', 'weekly')
            ->assertJsonPath('drafts.0.status', 'pending')
            ->assertJsonPath('drafts.0.stale', false)
            ->assertJsonStructure(['drafts' => [['id', 'payload' => ['draft' => ['assignments', 'unassigned']], 'base_version']]]);

        // The payload echoes the apply contract exactly.
        $this->assertArrayHasKey('base_version', $response->json('drafts.0.payload'));
    }

    public function test_apply_with_draft_id_marks_it_applied(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id);
        $this->prepareWeekly($user->id);

        $draft = app(ScheduleDraftRepository::class)->listPendingForUser($user->id)[0];
        $payload = $draft->payload;

        $response = $this->withToken($token)->postJson('/api/v1/schedule/draft/apply', [
            ...$payload,
            'draft_id' => $draft->id,
        ]);

        $response->assertStatus(200)->assertJsonPath('applied', true);
        $this->assertSame(2, app(ScheduleAssignmentRepository::class)->currentScheduleVersion($user->id)->value);

        $applied = app(ScheduleDraftRepository::class)->findForUser($user->id, (int) $draft->id);
        $this->assertNotNull($applied);
        $this->assertSame('applied', $applied->status->value);
        $this->assertCount(0, app(ScheduleDraftRepository::class)->listPendingForUser($user->id));

        // Apply acknowledges the review state (ADR-016 §2.3).
        $today = $this->withToken($token)->getJson('/api/v1/today?date='.now()->toDateString());
        $today->assertJsonPath('schedule_needs_review', false);
    }

    public function test_discard_lifecycle(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id);
        $this->prepareWeekly($user->id);
        $draft = app(ScheduleDraftRepository::class)->listPendingForUser($user->id)[0];

        $this->withToken($token)->postJson("/api/v1/schedule/drafts/{$draft->id}/discard")
            ->assertStatus(200)
            ->assertJsonPath('discarded', true);

        $this->assertCount(0, app(ScheduleDraftRepository::class)->listPendingForUser($user->id));
        $this->assertSame(
            'discarded',
            app(ScheduleDraftRepository::class)->findForUser($user->id, (int) $draft->id)?->status->value,
        );

        // Discarding again is rejected — the lifecycle is not re-entrant.
        $this->withToken($token)->postJson("/api/v1/schedule/drafts/{$draft->id}/discard")
            ->assertStatus(422);
    }

    public function test_unknown_draft_returns_404(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/schedule/drafts/999/discard')
            ->assertStatus(404);
    }

    public function test_drafts_are_owner_scoped(): void
    {
        [$userA] = $this->userWithToken();
        [$userB, $tokenB] = $this->userWithToken();
        $this->createTask($userA->id);
        $this->prepareWeekly($userA->id);

        $this->withToken($tokenB)->getJson('/api/v1/schedule/drafts')
            ->assertStatus(200)
            ->assertJsonCount(0, 'drafts');
    }

    public function test_stale_flag_is_derived_from_the_current_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);
        $this->prepareWeekly($user->id);

        // Version moves on after draft generation.
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: now()->toDateString(),
            startAt: now()->setTime(8, 0),
            endAt: now()->setTime(9, 0),
            source: ScheduleAssignmentSource::quickCapture(),
            scheduleVersion: 2,
        ));

        $response = $this->withToken($token)->getJson('/api/v1/schedule/drafts');

        $response->assertStatus(200)
            ->assertJsonPath('drafts.0.stale', true);
    }
}
