<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\MiniPauseUseCase;
use App\Domain\ActivityLogs\Contracts\ActivityLogRepository;
use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\TimeRange;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MiniPauseUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private MiniPauseUseCase $useCase;

    private ScheduleAssignmentRepository $assignments;

    private HardLandscapeRepository $hardLandscape;

    private ActivityLogRepository $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(MiniPauseUseCase::class);
        $this->assignments = app(ScheduleAssignmentRepository::class);
        $this->hardLandscape = app(HardLandscapeRepository::class);
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

    public function test_moves_all_eligible_tasks_to_next_day(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'Alpha', 1);
        $taskB = $this->createTask($user->id, 'Beta', 2);
        $this->place($user->id, $taskA->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $taskB->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertTrue($result->applied);
        $this->assertCount(2, $result->moves);
        $this->assertSame([], $result->conflictTaskIds);

        $movedTaskIds = array_map(static fn (array $move) => $move['task_id'], $result->moves);
        sort($movedTaskIds);
        $this->assertSame([(string) $taskA->id, (string) $taskB->id], $movedTaskIds);

        // Both assignments now live on the next day.
        $nextDay = $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-20'));
        $this->assertCount(2, $nextDay);
        $this->assertSame([], $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));

        // New placements carry the mini_pause source at the next schedule version.
        foreach ($nextDay as $assignment) {
            $this->assertTrue($assignment->source->equals(ScheduleAssignmentSource::miniPause()));
            $this->assertSame(2, $assignment->scheduleVersion);
        }
    }

    public function test_never_moves_locked_task(): void
    {
        $user = $this->createUser();
        $locked = $this->createTask($user->id, 'Locked', 3);
        $this->place($user->id, $locked->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00', locked: true);

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        // Locked task not moved and not a conflict.
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
        $this->assertSame([], $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-20')));
    }

    public function test_terminal_tasks_are_never_moved(): void
    {
        $user = $this->createUser();
        $completed = $this->createTask($user->id, 'Done', 1, null, 'completed');
        $this->place($user->id, $completed->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
    }

    public function test_conflict_when_next_day_fully_occupied(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Stuck', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        // Next day fully blocked by Hard Landscape.
        $this->addLandscape($user->id, '2026-08-20', '2026-08-20T00:00:00', '2026-08-21T00:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([(string) $task->id], $result->conflictTaskIds);
        // Task stays in place.
        $this->assertCount(1, $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19')));
    }

    public function test_respects_hard_landscape_on_next_day(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Meet-safe', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        // Landscape occupies 09:00–10:00 on the next day.
        $this->addLandscape($user->id, '2026-08-20', '2026-08-20T09:00:00', '2026-08-20T10:00:00');

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertTrue($result->applied);
        $this->assertCount(1, $result->moves);
        $nextDay = $this->assignments->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-20'));
        $this->assertCount(1, $nextDay);
        // Moved slot must not overlap the landscape.
        $this->assertFalse($nextDay[0]->timeRange()->overlaps(
            new TimeRange(
                CarbonImmutable::parse('2026-08-20T09:00:00'),
                CarbonImmutable::parse('2026-08-20T10:00:00'),
            ),
        ));
    }

    public function test_returns_noop_when_no_tasks_scheduled(): void
    {
        $user = $this->createUser();

        $result = $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertFalse($result->applied);
        $this->assertSame([], $result->moves);
        $this->assertSame([], $result->conflictTaskIds);
        $this->assertStringContainsString('No tasks', $result->explanation);
    }

    public function test_logs_mini_pause_activity(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id, 'Alpha', 1);
        $this->place($user->id, $task->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');

        $this->useCase->__invoke($user->id, CarbonImmutable::parse('2026-08-19'));

        $logs = $this->logs->listForUser($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame('mini_pause', $logs[0]->eventType->value);
        $this->assertSame('schedule', $logs[0]->entityType);
        $this->assertSame([(string) $task->id], $logs[0]->payload['moved_task_ids']);
        $this->assertSame(['2026-08-19'], [$logs[0]->payload['date']]);
    }
}
