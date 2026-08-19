<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ScheduleApiTest extends TestCase
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
            'status' => 'backlog',
            'priority_tier' => $overrides['priority_tier'] ?? 3,
            'program_id' => $overrides['program_id'] ?? null,
            'goal_id' => $overrides['goal_id'] ?? null,
            'milestone_id' => $overrides['milestone_id'] ?? null,
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

    public function test_today_requires_authentication(): void
    {
        $this->getJson('/api/v1/today?date=2026-08-19')->assertStatus(401);
        $this->getJson('/api/v1/schedule?date=2026-08-19')->assertStatus(401);
        $this->getJson('/api/v1/week')->assertStatus(401);
        $this->getJson('/api/v1/calendar')->assertStatus(401);
    }

    public function test_today_requires_date(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/today')->assertStatus(422);
    }

    public function test_today_returns_events_with_task_context_and_capacity(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Deep work');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonPath('date', '2026-08-19')
            ->assertJsonPath('schedule_version', 1)
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.task.title', 'Deep work')
            ->assertJsonPath('events.0.locked', false)
            ->assertJsonPath('events.0.conflict', false)
            ->assertJsonPath('events.0.assignment.duration_minutes', 60)
            ->assertJsonPath('capacity.scheduled_minutes', 60);
    }

    public function test_today_excludes_cancelled_assignments(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Cancelled');
        $assignment = app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T10:00:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));
        app(ScheduleAssignmentRepository::class)->update($assignment->cancel(), 1);

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(0, 'events');
    }

    public function test_today_marks_overlapping_assignments_as_conflict(): void
    {
        [$user, $token] = $this->userWithToken();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        // The assignment repository enforces no-overlap, so overlapping rows can
        // only exist as legacy/inconsistent data. Insert them directly to verify
        // the query view surfaces the conflict defensively (FR-01 business rule:
        // "task yang overlap ditandai conflict").
        foreach ([
            [$taskA->id, '2026-08-19T09:00:00', '2026-08-19T10:00:00'],
            [$taskB->id, '2026-08-19T09:30:00', '2026-08-19T10:30:00'],
        ] as [$taskId, $start, $end]) {
            DB::table('task_assignments')->insert([
                'user_id' => $user->id,
                'task_id' => $taskId,
                'date' => '2026-08-19',
                'start_at' => $start,
                'end_at' => $end,
                'duration_minutes' => 60,
                'status' => 'scheduled',
                'source' => 'draft',
                'schedule_version' => 1,
                'locked' => false,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $data = $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-19')
            ->assertStatus(200)
            ->json();

        $this->assertSame(120, $data['capacity']['scheduled_minutes']);
        $this->assertTrue(collect($data['events'])->contains('conflict', true));
    }

    public function test_schedule_range_returns_events_for_the_range(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Range task');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $task->id, '2026-08-20', '2026-08-20T10:00:00', '2026-08-20T11:00:00');

        $this->withToken($token)
            ->getJson('/api/v1/schedule?from=2026-08-19&to=2026-08-20')
            ->assertStatus(200)
            ->assertJsonPath('from', '2026-08-19')
            ->assertJsonPath('to', '2026-08-20')
            ->assertJsonCount(2, 'events');
    }

    public function test_schedule_requires_date_or_range(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/schedule')->assertStatus(422);
    }

    public function test_week_returns_seven_days(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Weekly');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->withToken($token)
            ->getJson('/api/v1/week?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.2.task_count', 1);
    }

    public function test_calendar_returns_month_summary(): void
    {
        [$user, $token] = $this->userWithToken();
        $task = $this->createTask($user->id, 'Monthly');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->withToken($token)
            ->getJson('/api/v1/calendar?month=2026-08')
            ->assertStatus(200)
            ->assertJsonPath('year', 2026)
            ->assertJsonPath('month', 8)
            ->assertJsonCount(31, 'days')
            ->assertJsonPath('days.18.task_count', 1);
    }

    public function test_schedule_is_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $otherTask = $this->createTask($other->id, 'Not mine');
        $this->place($other->id, $otherTask->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(0, 'events');
    }
}
