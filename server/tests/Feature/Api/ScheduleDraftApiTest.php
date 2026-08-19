<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleDraftApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, string $title = 'Task', array $overrides = []): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => $overrides['status'] ?? 'backlog',
            'priority_tier' => $overrides['priority_tier'] ?? 3,
            'program_id' => $overrides['program_id'] ?? null,
            'goal_id' => $overrides['goal_id'] ?? null,
            'milestone_id' => $overrides['milestone_id'] ?? null,
            'estimated_minutes' => $overrides['estimated_minutes'] ?? 60,
            'due_at' => $overrides['due_at'] ?? null,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    private function place(int $userId, int $taskId, string $date, string $start, string $end): void
    {
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $userId,
            taskId: $taskId,
            date: $date,
            startAt: $start,
            endAt: $end,
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));
    }

    private function block(int $userId, string $start, string $end): void
    {
        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, 'Block', HardLandscapeType::oneTime(), $start, $end),
        );
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])->assertStatus(401);
        $this->postJson('/api/v1/schedule/draft/apply', ['draft' => [], 'base_version' => 1])->assertStatus(401);
        $this->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])->assertStatus(401);
        $this->postJson('/api/v1/schedule/reschedule/apply', ['proposal' => [], 'base_version' => 1])->assertStatus(401);
    }

    public function test_draft_requires_dates(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/schedule/draft', [])->assertStatus(422);
        $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-18'])
            ->assertStatus(422);
    }

    public function test_draft_returns_assignments_and_base_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work', ['priority_tier' => 1]);

        $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->assertJsonPath('base_version', 1)
            ->assertJsonCount(1, 'draft.assignments')
            ->assertJsonCount(0, 'draft.unassigned')
            ->assertJsonPath('draft.assignments.0.task_id', (string) $task->id)
            ->assertJsonPath('draft.assignments.0.title', 'Deep work')
            ->assertJsonPath('draft.assignments.0.start', '2026-08-19T00:00:00.000000Z')
            ->assertJsonPath('draft.assignments.0.end', '2026-08-19T01:00:00.000000Z');
    }

    public function test_draft_reports_unassigned_tasks(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id, 'Huge', ['estimated_minutes' => 8000]);

        $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->assertJsonCount(0, 'draft.assignments')
            ->assertJsonCount(1, 'draft.unassigned')
            ->assertJsonPath('draft.unassigned.0.title', 'Huge')
            ->assertJsonPath('draft.unassigned.0.reason', 'NO_AVAILABLE_SLOT');
    }

    public function test_draft_only_includes_owners_tasks(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $this->createTask($user->id, 'Mine');
        $this->createTask($other->id, 'Not mine');

        $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->assertJsonCount(1, 'draft.assignments')
            ->assertJsonPath('draft.assignments.0.title', 'Mine');
    }

    public function test_draft_apply_persists_and_bumps_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work', ['priority_tier' => 1]);

        $draft = $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->json();

        $this->withToken($token)
            ->postJson('/api/v1/schedule/draft/apply', [
                'draft' => $draft['draft'],
                'base_version' => $draft['base_version'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('version', 2)
            ->assertJsonPath('applied', true)
            ->assertJsonCount(1, 'assignments');

        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_version' => 2,
            'source' => 'draft',
        ]);
    }

    public function test_draft_apply_rejects_stale_base_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id, 'Deep work');

        $draft = $this->withToken($token)
            ->postJson('/api/v1/schedule/draft', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->json();

        $this->withToken($token)->postJson('/api/v1/schedule/draft/apply', [
            'draft' => $draft['draft'],
            'base_version' => $draft['base_version'],
        ])->assertStatus(200);

        $stale = $draft['draft'];
        $stale['assignments'][0]['start'] = '2026-08-19T14:00:00.000000Z';
        $stale['assignments'][0]['end'] = '2026-08-19T15:00:00.000000Z';

        $this->withToken($token)->postJson('/api/v1/schedule/draft/apply', [
            'draft' => $stale,
            'base_version' => $draft['base_version'],
        ])->assertStatus(409);
    }

    public function test_draft_apply_rejects_foreign_task_with_404(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $foreign = $this->createTask($other->id, 'Not mine');

        $this->withToken($token)->postJson('/api/v1/schedule/draft/apply', [
            'draft' => [
                'assignments' => [[
                    'task_id' => (string) $foreign->id,
                    'title' => 'Not mine',
                    'start' => '2026-08-19T09:00:00.000000Z',
                    'end' => '2026-08-19T10:00:00.000000Z',
                ]],
                'unassigned' => [],
            ],
            'base_version' => 1,
        ])->assertStatus(404);
    }

    public function test_reschedule_returns_proposal_with_moves(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $this->block($user->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->assertJsonPath('proposal.base_version', 1)
            ->assertJsonPath('proposal.new_version', 2)
            ->assertJsonPath('has_changes', true)
            ->assertJsonCount(1, 'proposal.moves')
            ->assertJsonCount(0, 'proposal.conflict_task_ids')
            ->assertJsonPath('proposal.moves.0.task_id', (string) $task->id)
            ->assertJsonPath('proposal.moves.0.title', 'Deep work')
            ->assertJsonPath('proposal.moves.0.from.start', '2026-08-19T10:00:00.000000Z')
            ->assertJsonPath('proposal.moves.0.from.end', '2026-08-19T11:00:00.000000Z')
            ->assertJsonPath('proposal.moves.0.to.start', '2026-08-19T00:00:00.000000Z')
            ->assertJsonPath('proposal.moves.0.to.end', '2026-08-19T01:00:00.000000Z');
    }

    public function test_reschedule_flags_unplaceable_tasks_as_conflicts(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $this->block($user->id, '2026-08-19T00:00:00', '2026-08-20T00:00:00');

        $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->assertJsonCount(0, 'proposal.moves')
            ->assertJsonPath('has_changes', false)
            ->assertJsonPath('proposal.conflict_task_ids.0', (string) $task->id);
    }

    public function test_reschedule_apply_persists_and_bumps_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $this->block($user->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $proposal = $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->assertStatus(200)
            ->json()['proposal'];

        $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule/apply', [
                'proposal' => $proposal,
                'base_version' => $proposal['base_version'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('version', 2)
            ->assertJsonPath('applied', true)
            ->assertJsonCount(0, 'conflict_task_ids');

        $this->assertDatabaseHas('task_assignments', [
            'user_id' => $user->id,
            'task_id' => $task->id,
            'schedule_version' => 2,
            'source' => 'reschedule',
        ]);
    }

    public function test_reschedule_apply_rejects_stale_base_version(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');
        $this->block($user->id, '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $proposal = $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-08-19', 'to' => '2026-08-19'])
            ->json()['proposal'];

        $this->withToken($token)->postJson('/api/v1/schedule/reschedule/apply', [
            'proposal' => $proposal,
            'base_version' => $proposal['base_version'],
        ])->assertStatus(200);

        $stale = $proposal;
        $stale['moves'][0]['to']['start'] = '2026-08-19T14:00:00.000000Z';
        $stale['moves'][0]['to']['end'] = '2026-08-19T15:00:00.000000Z';

        $this->withToken($token)->postJson('/api/v1/schedule/reschedule/apply', [
            'proposal' => $stale,
            'base_version' => $proposal['base_version'],
        ])->assertStatus(409);
    }
}
