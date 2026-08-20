<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\EmergencyPauseUseCase;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use App\Domain\Pauses\Contracts\PauseEventRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EmergencyPauseUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private EmergencyPauseUseCase $useCase;

    private ScheduleAssignmentRepository $assignments;

    private HardLandscapeRepository $hardLandscape;

    private PauseEventRepository $pauseEvents;

    private ActivityLogRepository $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(EmergencyPauseUseCase::class);
        $this->assignments = app(ScheduleAssignmentRepository::class);
        $this->hardLandscape = app(HardLandscapeRepository::class);
        $this->pauseEvents = app(PauseEventRepository::class);
        $this->logs = app(ActivityLogRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createTask(int $userId, string $title, int $tier = 3, ?string $dueAt = null, string $status = 'scheduled'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => $status,
            'priority_tier' => $tier,
            'due_at' => $dueAt,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    private function place(int $userId, int $taskId, string $date, string $start, string $end, bool $locked = false): ScheduleAssignment
    {
        $assignment = ScheduleAssignment::create(
            userId: $userId,
            taskId: $taskId,
            date: $date,
            startAt: $start,
            endAt: $end,
            source: ScheduleAssignmentSource::manual(),
            scheduleVersion: 1,
            locked: $locked,
        );

        return $this->assignments->create($assignment);
    }

    private function addLandscape(int $userId, string $date, string $start, string $end): void
    {
        $this->hardLandscape->create(HardLandscapeEvent::create(
            $userId,
            'Team sync',
            HardLandscapeType::oneTime(),
            $start,
            $end,
        ));
    }

    public function test_shifts_all_eligible_tasks_to_next_week_except_kept(): void
    {
        $user = $this->createUser();
        $keepTask = $this->createTask($user->id, 'Keep me', 1);
        $taskB = $this->createTask($user->id, 'Shift me', 2);
        // Week anchor: 2026-08-19 (Wed). Keep task on Wed, move task on Thu.
        $this->place($user->id, $keepTask->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $taskB->id, '2026-08-20', '2026-08-20T09:00:00', '2026-08-20T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), [$keepTask->id]);

        $this->assertTrue($result->applied);
        $this->assertSame('2026-08-17', $result->weekStart); // Monday
        $this->assertSame('2026-08-23', $result->weekEnd);   // Sunday
        $this->assertSame([(string) $keepTask->id], $result->keepTaskIds);
        $this->assertCount(1, $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertSame((string) $taskB->id, $result->moves[0]['task_id']);

        // Kept task still this week; moved task lives on the same weekday next week.
        $kept = $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19'));
        $this->assertCount(1, $kept);
        $this->assertSame($keepTask->id, $kept[0]->taskId);

        $this->assertSame([], $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-20')));

        $moved = $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-27'));
        $this->assertCount(1, $moved);
        $this->assertSame($taskB->id, $moved[0]->taskId);
        $this->assertTrue($moved[0]->source->equals(ScheduleAssignmentSource::emergencyPause()));
        $this->assertSame(2, $moved[0]->scheduleVersion);
    }

    public function test_never_moves_locked_or_kept_tasks(): void
    {
        $user = $this->createUser();
        $locked = $this->createTask($user->id, 'Locked', 3);
        $keep = $this->createTask($user->id, 'Kept', 2);
        $this->place($user->id, $locked->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00', locked: true);
        $this->place($user->id, $keep->id, '2026-08-20', '2026-08-20T09:00:00', '2026-08-20T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), [$keep->id]);

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-20')));
        $this->assertSame([], $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-26')));
        $this->assertSame([], $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-27')));
    }

    public function test_terminal_tasks_are_never_moved(): void
    {
        $user = $this->createUser();
        $completed = $this->createTask($user->id, 'Done', 1, null, 'completed');
        $this->place($user->id, $completed->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
    }

    public function test_conflict_when_next_week_day_fully_occupied(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Stuck', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        // Same weekday next week fully blocked by Hard Landscape.
        $this->addLandscape($user->id, '2026-08-26', '2026-08-26T00:00:00', '2026-08-27T00:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        // The week is still tagged exceptional with the conflict visible.
        $this->assertTrue($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([(string) $task->id], $result->conflictTaskIds);
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
    }

    public function test_respects_hard_landscape_on_next_week_day(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Meet-safe', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        // Landscape occupies 09:00–10:00 on the same weekday next week.
        $this->addLandscape($user->id, '2026-08-26', '2026-08-26T09:00:00', '2026-08-26T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        $this->assertTrue($result->applied);
        $this->assertCount(1, $result->moves);
        $moved = $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-26'));
        $this->assertCount(1, $moved);
        // Moved slot must not overlap the landscape.
        $this->assertNotSame('09:00', $moved[0]->startAt->format('H:i'));
    }

    public function test_returns_noop_when_no_eligible_tasks_scheduled(): void
    {
        $user = $this->createUser();

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertStringContainsString('No eligible tasks', $result->explanation);
    }

    public function test_tags_week_exceptional_and_logs_activity(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Alpha', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        // Week tagged exceptional.
        $pause = $this->pauseEvents->findEmergencyForWeek($user->id, CarbonImmutable::parse('2026-08-19'));
        $this->assertNotNull($pause);
        $this->assertTrue($pause->isEmergency());
        $this->assertSame('2026-08-17', $pause->weekStart->toDateString());
        $this->assertSame('2026-08-23', $pause->weekEnd->toDateString());
        $this->assertSame([(string) $task->id], $pause->movedTaskIds);
        $this->assertSame([], $pause->conflictTaskIds);

        // Notification suppression surface.
        $this->assertTrue($this->pauseEvents->isWeekExceptional($user->id, CarbonImmutable::parse('2026-08-20')));

        // Activity logged.
        $logs = $this->logs->listForUser($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame('emergency_pause', $logs[0]->eventType->value);
        $this->assertSame('schedule', $logs[0]->entityType);
        $this->assertSame(['2026-08-17'], [$logs[0]->payload['week_start']]);
        $this->assertSame(['2026-08-23'], [$logs[0]->payload['week_end']]);
    }

    public function test_preserves_task_ownership_and_never_deletes_tasks(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Alpha', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'), []);

        // Task still exists with its owner; the assignment moved.
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('task_assignments', ['task_id' => $task->id, 'date' => '2026-08-19 00:00:00']);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id, 'date' => '2026-08-26 00:00:00']);
    }
}
