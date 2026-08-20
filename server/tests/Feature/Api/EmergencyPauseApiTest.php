<?php

namespace Tests\Feature\Api;

use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmergencyPauseApiTest extends TestCase
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
        $this->postJson('/api/v1/schedule/emergency-pause', [
            'date' => '2026-08-19',
            'keep_task_ids' => [],
        ])->assertStatus(401);
    }

    public function test_moves_tasks_and_tags_week(): void
    {
        [$user, $token] = $this->userWithToken();
        $keep = $this->createTask($user->id, 'Keep');
        $move = $this->createTask($user->id, 'Move');
        $this->place($user->id, $keep->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $move->id, '2026-08-20', '2026-08-20T09:00:00', '2026-08-20T10:00:00');

        $response = $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', [
                'date' => '2026-08-19',
                'keep_task_ids' => [$keep->id],
            ]);

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $response->assertJsonPath('version', 2);
        $response->assertJsonPath('week_start', '2026-08-17');
        $response->assertJsonPath('week_end', '2026-08-23');
        $response->assertJsonPath('keep_task_ids', [(string) $keep->id]);
        $response->assertJsonCount(1, 'moves');
        $response->assertJsonPath('moves.0.task_id', (string) $move->id);
        $response->assertJsonPath('conflict_task_ids', []);
        $response->assertJsonStructure(['explanation']);

        $pause = app(PauseEventRepository::class)->findEmergencyForWeek(
            $user->id,
            CarbonImmutable::parse('2026-08-19'),
        );
        $this->assertNotNull($pause);
        $this->assertSame([(string) $move->id], $pause->movedTaskIds);
    }

    public function test_returns_202_when_nothing_eligible(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createTask($user->id, 'Alpha');

        $response = $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', [
                'date' => '2026-08-19',
                'keep_task_ids' => [],
            ]);

        $response->assertStatus(202);
        $response->assertJsonPath('applied', false);
    }

    public function test_validates_date_and_keep_task_ids(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', ['date' => 'not-a-date', 'keep_task_ids' => []])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', ['date' => '2026-08-19'])
            ->assertStatus(422);

        $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', ['date' => '2026-08-19', 'keep_task_ids' => ['abc']])
            ->assertStatus(422);
    }

    public function test_today_reports_recovery_state_after_emergency_pause(): void
    {
        [$user, $token] = $this->userWithToken();
        $move = $this->createTask($user->id, 'Move');
        $this->place($user->id, $move->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->withToken($token)
            ->postJson('/api/v1/schedule/emergency-pause', [
                'date' => '2026-08-19',
                'keep_task_ids' => [],
            ])
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-20')
            ->assertOk()
            ->assertJsonPath('pause.type', 'emergency')
            ->assertJsonPath('pause.week_start', '2026-08-17')
            ->assertJsonPath('pause.week_end', '2026-08-23');
    }
}
