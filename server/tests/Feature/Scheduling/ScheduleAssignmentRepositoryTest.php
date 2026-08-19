<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ScheduleAssignmentOverlap;
use App\Domain\Scheduling\ScheduleAssignmentVersionConflict;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class ScheduleAssignmentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleAssignmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ScheduleAssignmentRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function createTask(int $userId, string $title = 'Task'): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $title,
            'status' => 'backlog',
            'priority_tier' => 3,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
    }

    public function test_assignment_can_be_created_and_retrieved(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $assignment = $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 2,
        ));

        $this->assertGreaterThan(0, $assignment->id);
        $this->assertSame($task->id, $assignment->taskId);
        $this->assertSame(45, $assignment->durationMinutes);
        $this->assertTrue($assignment->source->equals(ScheduleAssignmentSource::draft()));
        $this->assertSame(2, $assignment->scheduleVersion);
        $this->assertNotNull($assignment->createdAt);

        $found = $this->repository->findForUser($user->id, $assignment->id);
        $this->assertNotNull($found);
        $this->assertSame($assignment->id, $found->id);
    }

    public function test_create_rejects_unknown_task(): void
    {
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: 999,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));
    }

    public function test_create_rejects_cross_user_task(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $task = $this->createTask($other->id);

        $this->expectException(InvalidArgumentException::class);

        $this->repository->create(ScheduleAssignment::create(
            userId: $owner->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));
    }

    public function test_create_rejects_overlapping_assignment(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskA->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->expectException(ScheduleAssignmentOverlap::class);

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskB->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:30:00',
            endAt: '2026-08-19T10:00:00',
        ));
    }

    public function test_adjacent_assignments_are_allowed(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskA->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $second = $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskB->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:45:00',
            endAt: '2026-08-19T10:30:00',
        ));

        $this->assertNotNull($second);
    }

    public function test_list_for_user_on_date(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-20',
            startAt: '2026-08-20T09:00:00',
            endAt: '2026-08-20T09:45:00',
        ));

        $nineteenth = $this->repository->listForUserOnDate($user->id, CarbonImmutable::parse('2026-08-19'));

        $this->assertCount(1, $nineteenth);
        $this->assertSame('2026-08-19', $nineteenth[0]->date->toDateString());
    }

    public function test_list_for_user_in_range(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-18',
            startAt: '2026-08-18T22:00:00',
            endAt: '2026-08-18T23:00:00',
        ));

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-21',
            startAt: '2026-08-21T09:00:00',
            endAt: '2026-08-21T09:45:00',
        ));

        $range = $this->repository->listForUserInRange(
            $user->id,
            CarbonImmutable::parse('2026-08-19T00:00:00'),
            CarbonImmutable::parse('2026-08-20T00:00:00'),
        );

        $this->assertCount(1, $range);
        $this->assertSame('2026-08-19', $range[0]->date->toDateString());
    }

    public function test_list_for_task(): void
    {
        $user = $this->createUser();
        $taskA = $this->createTask($user->id, 'A');
        $taskB = $this->createTask($user->id, 'B');

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskA->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $taskB->id,
            date: '2026-08-19',
            startAt: '2026-08-19T10:00:00',
            endAt: '2026-08-19T10:45:00',
        ));

        $this->assertCount(1, $this->repository->listForTask($taskA->id));
        $this->assertCount(1, $this->repository->listForTask($taskB->id));
    }

    public function test_update_bumps_version_and_persists_changes(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $assignment = $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
            locked: false,
        ));

        $updated = $this->repository->update($assignment->withLocked(true), $assignment->version);

        $this->assertTrue($updated->locked);
        $this->assertSame(2, $updated->version);

        $this->assertDatabaseHas('task_assignments', [
            'id' => $assignment->id,
            'locked' => true,
            'version' => 2,
        ]);
    }

    public function test_update_with_stale_version_throws_conflict(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $assignment = $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $firstMutation = $assignment->withLocked(true);
        $this->repository->update($firstMutation, $assignment->version);

        $this->expectException(ScheduleAssignmentVersionConflict::class);

        $this->repository->update($assignment->withLocked(true), $assignment->version);
    }

    public function test_delete_removes_assignment(): void
    {
        $user = $this->createUser();
        $task = $this->createTask($user->id);

        $assignment = $this->repository->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->repository->deleteForUser($user->id, $assignment->id);

        $this->assertNull($this->repository->findForUser($user->id, $assignment->id));
    }

    public function test_delete_missing_assignment_throws(): void
    {
        $user = $this->createUser();

        $this->expectException(InvalidArgumentException::class);

        $this->repository->deleteForUser($user->id, 999);
    }

    public function test_repository_is_scoped_to_owner(): void
    {
        $owner = $this->createUser();
        $other = $this->createUser();
        $task = $this->createTask($owner->id);

        $assignment = $this->repository->create(ScheduleAssignment::create(
            userId: $owner->id,
            taskId: $task->id,
            date: '2026-08-19',
            startAt: '2026-08-19T09:00:00',
            endAt: '2026-08-19T09:45:00',
        ));

        $this->assertNull($this->repository->findForUser($other->id, $assignment->id));
        $this->assertCount(0, $this->repository->listForUserOnDate($other->id, CarbonImmutable::parse('2026-08-19')));
    }
}
