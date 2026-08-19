<?php

namespace Tests\Feature\Api;

use App\Application\Progress\RecordProgressEventUseCase;
use App\Domain\Progress\ProgressEvent;
use App\Domain\Progress\ValueObjects\ProgressEventType;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressEventsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => 'Task',
            'status' => 'scheduled',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    private function createMilestone(int $userId): Milestone
    {
        $goal = Goal::query()->create([
            'user_id' => $userId,
            'title' => 'Goal',
            'horizon' => 'quarterly',
            'status' => 'active',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'outcome' => 'Outcome',
            'why' => 'Because',
        ]);

        return Milestone::query()->create([
            'goal_id' => $goal->id,
            'user_id' => $userId,
            'title' => 'Milestone',
            'sequence' => 1,
            'progress_mode' => 'derived',
            'progress' => 0,
            'status' => 'planned',
            'version' => 1,
        ]);
    }

    public function test_progress_events_require_authentication(): void
    {
        $this->getJson('/api/v1/progress')->assertStatus(401);
        $this->postJson('/api/v1/progress', [
            'event_type' => 'evidence_attached',
            'title' => 'Added evidence',
        ])->assertStatus(401);
    }

    public function test_list_is_empty_initially(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/progress')
            ->assertStatus(200)
            ->assertJsonCount(0, 'events');
    }

    public function test_manual_event_can_be_recorded(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/progress', [
            'event_type' => 'experiment_recorded',
            'title' => 'Ran the A/B experiment',
            'entity_type' => 'note',
            'entity_id' => 99,
            'occurred_at' => '2026-08-18 10:00:00',
        ])->assertStatus(201)
            ->assertJsonPath('event.event_type', 'experiment_recorded')
            ->assertJsonPath('event.entity_type', 'note');

        $this->assertDatabaseHas('progress_events', [
            'user_id' => $user->id,
            'event_type' => 'experiment_recorded',
        ]);
    }

    public function test_derived_types_are_not_manually_recordable(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/progress', [
            'event_type' => 'task_completed',
            'title' => 'Nope',
        ])->assertStatus(422);

        $this->assertDatabaseCount('progress_events', 0);
    }

    public function test_completing_a_task_generates_a_progress_event(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'in_progress'])
            ->assertStatus(200);

        $this->withToken($token)->postJson("/api/v1/tasks/{$task->id}/status", ['status' => 'completed'])
            ->assertStatus(200);

        $this->assertDatabaseHas('progress_events', [
            'user_id' => $user->id,
            'event_type' => 'task_completed',
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'operation_id' => "task:completed:{$task->id}",
        ]);
    }

    public function test_milestone_lifecycle_generates_advance_and_complete_events(): void
    {
        [$user, $token] = $this->userWithToken();
        $milestone = $this->createMilestone($user->id);

        $this->withToken($token)->postJson(
            "/api/v1/goals/{$milestone->goal_id}/milestones/{$milestone->id}/status",
            ['status' => 'active'],
        )->assertStatus(200);

        $this->assertDatabaseHas('progress_events', [
            'user_id' => $user->id,
            'event_type' => 'milestone_advanced',
            'entity_id' => $milestone->id,
        ]);

        $this->withToken($token)->postJson(
            "/api/v1/goals/{$milestone->goal_id}/milestones/{$milestone->id}/status",
            ['status' => 'completed'],
        )->assertStatus(200);

        $this->assertDatabaseHas('progress_events', [
            'user_id' => $user->id,
            'event_type' => 'milestone_completed',
            'entity_id' => $milestone->id,
        ]);
    }

    public function test_direct_completion_emits_only_the_completed_event(): void
    {
        [$user, $token] = $this->userWithToken();
        $milestone = $this->createMilestone($user->id);

        $this->withToken($token)->postJson(
            "/api/v1/goals/{$milestone->goal_id}/milestones/{$milestone->id}/status",
            ['status' => 'completed'],
        )->assertStatus(200);

        $this->assertDatabaseHas('progress_events', [
            'event_type' => 'milestone_completed',
            'entity_id' => $milestone->id,
        ]);

        $this->assertDatabaseMissing('progress_events', [
            'event_type' => 'milestone_advanced',
            'entity_id' => $milestone->id,
        ]);
    }

    public function test_append_is_idempotent_by_operation_id(): void
    {
        [$user, $token] = $this->userWithToken();

        $record = app(RecordProgressEventUseCase::class);

        $record->__invoke(ProgressEvent::create(
            $user->id,
            ProgressEventType::taskCompleted(),
            'task',
            1,
            'Done',
            operationId: 'task:completed:1',
        ));
        $record->__invoke(ProgressEvent::create(
            $user->id,
            ProgressEventType::taskCompleted(),
            'task',
            1,
            'Done',
            operationId: 'task:completed:1',
        ));

        $this->withToken($token)->getJson('/api/v1/progress')
            ->assertStatus(200)
            ->assertJsonCount(1, 'events');
    }

    public function test_events_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $this->withToken($token)->postJson('/api/v1/progress', [
            'event_type' => 'goal_progress',
            'title' => 'Progress!',
        ])->assertStatus(201);

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/v1/progress')
            ->assertStatus(200)
            ->assertJsonCount(0, 'events');
    }

    public function test_events_can_be_filtered_by_type(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/progress', [
            'event_type' => 'goal_progress',
            'title' => 'Progress!',
        ])->assertStatus(201);

        $this->withToken($token)->getJson('/api/v1/progress?event_type=evidence_attached')
            ->assertStatus(200)
            ->assertJsonCount(0, 'events');

        $this->withToken($token)->getJson('/api/v1/progress?event_type=goal_progress')
            ->assertStatus(200)
            ->assertJsonCount(1, 'events');
    }
}
