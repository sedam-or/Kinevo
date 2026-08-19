<?php

namespace Tests\Feature\Scheduling;

use App\Application\Scheduling\QuickCapturePlacementUseCase;
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

final class QuickCapturePlacementUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private QuickCapturePlacementUseCase $useCase;

    private ScheduleAssignmentRepository $assignments;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useCase = app(QuickCapturePlacementUseCase::class);
        $this->assignments = app(ScheduleAssignmentRepository::class);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    public function test_places_task_when_slot_available(): void
    {
        $user = $this->createUser();
        $today = CarbonImmutable::parse('2026-08-19');

        $result = $this->useCase->__invoke(
            $user->id,
            'Write report',
            2,
            null,
            30,
            null,
            null,
            $today,
        );

        $this->assertTrue($result->placed);
        $this->assertNotNull($result->assignment);
        $this->assertSame('PLACED', $result->code);
        $this->assertSame('2026-08-19', $result->assignment->date->toDateString());
        $this->assertSame(30, $result->assignment->durationMinutes);
        $this->assertTrue($result->assignment->source->equals(ScheduleAssignmentSource::quickCapture()));

        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'Write report']);
    }

    public function test_returns_no_capacity_when_day_is_full(): void
    {
        $user = $this->createUser();
        $today = CarbonImmutable::parse('2026-08-19');

        // Fill the day with an existing assignment, leaving only a sub-15-minute
        // tail gap so no fillable slot remains for a 60-minute task.
        $existing = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Existing',
            'status' => 'scheduled',
            'priority_tier' => 1,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);
        $this->assignments->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $existing->id,
            date: '2026-08-19',
            startAt: '2026-08-19T00:00:00',
            endAt: '2026-08-19T23:58:00',
            source: ScheduleAssignmentSource::manual(),
            scheduleVersion: 1,
        ));

        $result = $this->useCase->__invoke($user->id, 'No room', 3, null, 60, null, null, $today);

        $this->assertFalse($result->placed);
        $this->assertNull($result->assignment);
        $this->assertSame('TASK_NO_CAPACITY', $result->code);
        $this->assertSame(['manual_swap', 'auto_swap', 'schedule_later'], $result->strategies);

        // Task is never lost.
        $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => 'No room']);
    }

    public function test_applies_size_default_duration(): void
    {
        $user = $this->createUser();
        $today = CarbonImmutable::parse('2026-08-19');

        $result = $this->useCase->__invoke($user->id, 'Quick note', 3, 'cepat', null, null, null, $today);

        $this->assertTrue($result->placed);
        $this->assertSame(15, $result->assignment->durationMinutes);
    }

    public function test_avoids_hard_landscape_overlap(): void
    {
        $user = $this->createUser();
        $today = CarbonImmutable::parse('2026-08-19');

        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create(
                $user->id,
                'Meeting',
                HardLandscapeType::oneTime(),
                '2026-08-19T09:00:00',
                '2026-08-19T09:30:00',
            ),
        );

        $result = $this->useCase->__invoke($user->id, 'Deep work', 3, null, 30, null, null, $today);

        $this->assertTrue($result->placed);
        $landscape = app(HardLandscapeRepository::class)
            ->listForUserOnDate($user->id, $today)[0];

        $this->assertFalse($result->assignment->timeRange()->overlaps($landscape->timeRange()));
    }
}
