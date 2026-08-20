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

final class ScheduleExportApiTest extends TestCase
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

    private function createHardLandscape(
        int $userId,
        string $title,
        HardLandscapeType $type,
        string $start,
        string $end,
        ?string $recurrence = null,
    ): HardLandscapeEvent {
        return app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, $title, $type, $start, $end, $recurrence),
        );
    }

    public function test_export_requires_authentication(): void
    {
        $this->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')->assertStatus(401);
    }

    public function test_export_validates_range(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->get('/api/v1/schedule/export/ics')->assertStatus(422);
        $this->withToken($token)->get('/api/v1/schedule/export/ics?from=2026-08-26&to=2026-08-19')->assertStatus(422);
    }

    public function test_export_returns_valid_ical_with_assignments_and_hard_landscape(): void
    {
        [$user, $token] = $this->userWithToken();

        $task = $this->createTask($user->id, 'Deep work, Part 1');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->createHardLandscape($user->id, 'Team Standup', HardLandscapeType::oneTime(), '2026-08-19T13:00:00', '2026-08-19T13:30:00');

        $response = $this->withToken($token)
            ->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="kinevo-schedule.ics"');

        $content = (string) $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $content);
        $this->assertStringContainsString('SUMMARY:Deep work\\, Part 1', $content);
        $this->assertStringContainsString('DTSTART:20260819T090000Z', $content);
        $this->assertStringContainsString('DTEND:20260819T100000Z', $content);
        $this->assertStringContainsString('SUMMARY:Team Standup', $content);
        $this->assertStringContainsString('DTSTART:20260819T130000Z', $content);
        $this->assertStringContainsString('DTEND:20260819T133000Z', $content);
        $this->assertSame(2, substr_count($content, 'BEGIN:VEVENT'));
    }

    public function test_export_expands_recurring_hard_landscape_within_window(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->createHardLandscape(
            $user->id,
            'Weekly Class',
            HardLandscapeType::recurring(),
            '2026-08-19T13:00:00',
            '2026-08-19T15:30:00',
            'FREQ=WEEKLY;BYDAY=WE;COUNT=12',
        );

        $content = (string) $this->withToken($token)
            ->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($content, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('DTSTART:20260819T130000Z', $content);
        $this->assertStringContainsString('DTEND:20260819T153000Z', $content);
        $this->assertStringContainsString('DTSTART:20260826T130000Z', $content);
        $this->assertStringContainsString('DTEND:20260826T153000Z', $content);
        $this->assertStringNotContainsString('RRULE:', $content);
    }

    public function test_export_excludes_cancelled_assignments(): void
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

        $content = (string) $this->withToken($token)
            ->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($content, 'BEGIN:VEVENT'));
        $this->assertStringNotContainsString('Cancelled', $content);
    }

    public function test_export_does_not_leak_internal_identifiers(): void
    {
        [$user, $token] = $this->userWithToken();

        $task = $this->createTask($user->id, 'Private');
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $content = (string) $this->withToken($token)
            ->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/UID:kinevo-[0-9a-f]{20}@kinevo/', $content);
        $this->assertStringNotContainsString('user_id', $content);
        $this->assertStringNotContainsString('task_id', $content);
        $this->assertStringNotContainsString('"id"', $content);
    }

    public function test_export_only_includes_own_data(): void
    {
        [$user, $token] = $this->userWithToken();
        [$other, $otherToken] = $this->userWithToken();

        $otherTask = $this->createTask($other->id, 'Other Private');
        $this->place($other->id, $otherTask->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $content = (string) $this->withToken($token)
            ->get('/api/v1/schedule/export/ics?from=2026-08-19&to=2026-08-26')
            ->assertOk()
            ->getContent();

        $this->assertSame(0, substr_count($content, 'BEGIN:VEVENT'));
        $this->assertStringNotContainsString('Other Private', $content);
    }
}
