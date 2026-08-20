<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MiniPauseApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, string $title = 'Task'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'scheduled',
            'priority_tier' => 3,
            'estimated_minutes' => 60,
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
            source: ScheduleAssignmentSource::manual(),
            scheduleVersion: 1,
        ));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/schedule/mini-pause', ['date' => '2026-08-19'])->assertStatus(401);
    }

    public function test_moves_tasks_and_returns_explanation(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Alpha');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $response = $this->withToken($token)
            ->postJson('/api/v1/schedule/mini-pause', ['date' => '2026-08-19']);

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $response->assertJsonPath('version', 2);
        $response->assertJsonCount(1, 'moves');
        $response->assertJsonPath('moves.0.task_id', (string) $task->id);
        $response->assertJsonPath('conflict_task_ids', []);
        $response->assertJsonStructure(['explanation']);
        $this->assertStringContainsString('2026-08-20', $response->json('explanation'));
    }

    public function test_returns_202_when_nothing_eligible(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Alpha');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        // Lock the assignment so it is not eligible.
        $repo = app(ScheduleAssignmentRepository::class);
        $assignment = $repo->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19'))[0];
        $repo->update($assignment->withLocked(true), $assignment->version);

        $response = $this->withToken($token)
            ->postJson('/api/v1/schedule/mini-pause', ['date' => '2026-08-19']);

        $response->assertStatus(202);
        $response->assertJsonPath('applied', false);
        $response->assertJsonCount(0, 'moves');
    }

    public function test_validates_date_required(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)
            ->postJson('/api/v1/schedule/mini-pause', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');
    }
}
