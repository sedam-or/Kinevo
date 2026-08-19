<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\AutoSwapUseCase;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutoSwapUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private AutoSwapUseCase $useCase;

    private ScheduleAssignmentRepository $assignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(AutoSwapUseCase::class);
        $this->assignments = app(ScheduleAssignmentRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createTask(int $userId, string $title, int $tier = 3, ?string $dueAt = null): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'scheduled',
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

    public function test_swaps_lowest_priority_unlocked_task_and_places_new_task(): void
    {
        $user = $this->createUser();
        $high = $this->createTask($user->id, 'High priority', 1);
        $low = $this->createTask($user->id, 'Low priority', 3);
        $this->place($user->id, $high->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $low->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $newTask = $this->createTask($user->id, 'New urgent', 1);

        $result = $this->useCase->__invoke($user->id, $newTask->id, CarbonImmutable::parse('2026-08-19'), 60);

        $this->assertTrue($result->applied);
        $this->assertNotNull($result->assignment);
        $this->assertSame($newTask->id, $result->assignment->taskId);
        $this->assertSame($low->id, $result->swappedTask->id);

        // New task placed in the vacated 10:00 slot.
        $this->assertSame('2026-08-19 10:00:00', $result->assignment->startAt->toDateTimeString());
        // Low-priority task moved to next day.
        $this->assertSame('2026-08-20', $result->movedTo->start->toDateString());
    }

    public function test_never_swaps_locked_task(): void
    {
        $user = $this->createUser();
        $locked = $this->createTask($user->id, 'Locked task', 3);
        $this->place($user->id, $locked->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00', locked: true);

        $newTask = $this->createTask($user->id, 'New task', 1);

        $result = $this->useCase->__invoke($user->id, $newTask->id, CarbonImmutable::parse('2026-08-19'), 60);

        $this->assertFalse($result->applied);
        $this->assertNull($result->assignment);
        $this->assertSame($locked->id, $result->swappedTask->id);

        // Locked task not moved.
        $this->assertNull($result->movedTo);
        // New task not deleted.
        $this->assertDatabaseHas('tasks', ['id' => $newTask->id]);
    }

    public function test_returns_no_candidate_when_day_has_no_unlocked_tasks(): void
    {
        $user = $this->createUser();
        $newTask = $this->createTask($user->id, 'New task', 1);

        $result = $this->useCase->__invoke($user->id, $newTask->id, CarbonImmutable::parse('2026-08-19'), 60);

        $this->assertFalse($result->applied);
        $this->assertNull($result->assignment);
        $this->assertNull($result->swappedTask);
    }

    public function test_farthest_deadline_is_tie_breaker(): void
    {
        $user = $this->createUser();
        $near = $this->createTask($user->id, 'Near deadline', 3, '2026-08-20');
        $far = $this->createTask($user->id, 'Far deadline', 3, '2026-08-30');
        $this->place($user->id, $near->id, '2026-08-19', '2026-08-19T09:00:00', '2026-08-19T10:00:00');
        $this->place($user->id, $far->id, '2026-08-19', '2026-08-19T10:00:00', '2026-08-19T11:00:00');

        $newTask = $this->createTask($user->id, 'New', 1);

        $result = $this->useCase->__invoke($user->id, $newTask->id, CarbonImmutable::parse('2026-08-19'), 60);

        $this->assertTrue($result->applied);
        // The far-deadline task (same priority, farther deadline) is swapped out.
        $this->assertSame($far->id, $result->swappedTask->id);
    }
}
