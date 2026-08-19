<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutoSwapApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createTask(int $userId, string $title, int $tier = 3): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'scheduled',
            'priority_tier' => $tier,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    private function place(int $userId, int $taskId, string $date, string $start, string $end): void
    {
        app(ScheduleAssignmentRepository::class)->create(
            ScheduleAssignment::create(
                userId: $userId,
                taskId: $taskId,
                date: $date,
                startAt: $start,
                endAt: $end,
                source: ScheduleAssignmentSource::manual(),
                scheduleVersion: 1,
            ),
        );
    }

    public function test_auto_swap_requires_authentication(): void
    {
        $this->postJson('/api/v1/tasks/1/auto-swap', [])->assertStatus(401);
    }

    public function test_auto_swap_places_task_and_moves_candidate(): void
    {
        [$user, $token] = $this->userWithToken();
        $candidate = $this->createTask($user->id, 'Low priority', 3);
        $this->place($user->id, $candidate->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $newTask = $this->createTask($user->id, 'New urgent', 1);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$newTask->id}/auto-swap", [
                'date' => '2026-08-19',
                'duration_minutes' => 60,
            ])
            ->assertStatus(200)
            ->assertJsonPath('applied', true)
            ->assertJsonPath('task.title', 'New urgent')
            ->assertJsonPath('swapped_task.id', $candidate->id);
    }

    public function test_auto_swap_returns_202_when_no_safe_candidate(): void
    {
        [$user, $token] = $this->userWithToken();
        $newTask = $this->createTask($user->id, 'New task', 1);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$newTask->id}/auto-swap", [
                'date' => '2026-08-19',
                'duration_minutes' => 60,
            ])
            ->assertStatus(202)
            ->assertJsonPath('applied', false);
    }

    public function test_auto_swap_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();
        $newTask = $this->createTask($user->id, 'New', 1);

        $this->withToken($token)
            ->postJson("/api/v1/tasks/{$newTask->id}/auto-swap", [])
            ->assertStatus(422);
    }
}
