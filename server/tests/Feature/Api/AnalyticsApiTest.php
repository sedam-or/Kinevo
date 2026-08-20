<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\ActivityLog;
use App\Models\FocusSession;
use App\Models\Goal;
use App\Models\Milestone;
use App\Models\Program;
use App\Models\ProgressEvent;
use App\Models\RechargeSession;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function addFocusSession(int $userId, string $endedAt, int $durationMinutes = 25): FocusSession
    {
        return FocusSession::query()->create([
            'user_id' => $userId,
            'task_id' => null,
            'started_at' => Carbon::parse($endedAt)->subMinutes($durationMinutes),
            'ended_at' => Carbon::parse($endedAt),
            'duration_minutes' => $durationMinutes,
        ]);
    }

    private function addRecharge(int $userId, string $endedAt, int $durationMinutes): RechargeSession
    {
        return RechargeSession::query()->create([
            'user_id' => $userId,
            'started_at' => Carbon::parse($endedAt)->subMinutes($durationMinutes),
            'ended_at' => Carbon::parse($endedAt),
            'duration_minutes' => $durationMinutes,
            'status' => 'completed',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_work_life_requires_authentication(): void
    {
        $this->getJson('/api/v1/analytics/work-life')->assertStatus(401);
        $this->getJson('/api/v1/analytics/overview')->assertStatus(401);
        $this->getJson('/api/v1/analytics/pillars')->assertStatus(401);
    }

    public function test_work_life_aggregates_productive_and_recharge_minutes(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->addFocusSession($user->id, '2026-08-18 10:00:00', 50);
        $this->addFocusSession($user->id, '2026-08-19 10:00:00', 25);
        $this->addRecharge($user->id, '2026-08-19 10:25:00', 15);

        $this->withToken($token)->getJson('/api/v1/analytics/work-life?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('from', '2026-08-18')
            ->assertJsonPath('to', '2026-08-19')
            ->assertJsonPath('productive_minutes', 75)
            ->assertJsonPath('recharge_minutes', 15)
            ->assertJsonPath('total_minutes', 90)
            ->assertJsonPath('work_ratio', fn ($v) => abs((float) $v - 75 / 90) < 0.0001)
            ->assertJsonPath('recharge_ratio', fn ($v) => abs((float) $v - 15 / 90) < 0.0001)
            ->assertJsonPath('disclaimer', 'Time-balance indicator. Not a health diagnosis.')
            ->assertJsonPath('days.0.date', '2026-08-18')
            ->assertJsonPath('days.0.productive_minutes', 50)
            ->assertJsonPath('days.1.date', '2026-08-19')
            ->assertJsonPath('days.1.productive_minutes', 25)
            ->assertJsonPath('days.1.recharge_minutes', 15);
    }

    public function test_work_life_defaults_to_current_week_and_scopes_by_user(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        Carbon::setTestNow('2026-08-20 12:00:00');

        $this->addFocusSession($user->id, '2026-08-18 10:00:00', 60);
        $this->addFocusSession($other->id, '2026-08-19 10:00:00', 500);

        $this->withToken($token)->getJson('/api/v1/analytics/work-life')
            ->assertOk()
            ->assertJsonPath('from', '2026-08-17')
            ->assertJsonPath('productive_minutes', 60)
            ->assertJsonPath('work_ratio', fn ($v) => (float) $v === 1.0);
    }

    public function test_work_life_returns_zero_ratios_without_data(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/analytics/work-life?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('productive_minutes', 0)
            ->assertJsonPath('recharge_minutes', 0)
            ->assertJsonPath('work_ratio', fn ($v) => (float) $v === 0.0)
            ->assertJsonPath('band', 'no_data')
            ->assertJsonPath('days', fn ($days) => count($days) === 2);
    }

    public function test_work_life_rejects_inverted_range(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/analytics/work-life?from=2026-08-19&to=2026-08-18')
            ->assertStatus(422);
    }

    public function test_overview_returns_all_read_models_for_the_period(): void
    {
        [$user, $token] = $this->userWithToken();

        Task::query()->create([
            'user_id' => $user->id, 'title' => 'Done task', 'status' => 'completed',
            'priority_tier' => 1, 'progress_mode' => 'derived', 'progress' => 100, 'version' => 1,
        ]);
        Task::query()->create([
            'user_id' => $user->id, 'title' => 'Scheduled task', 'status' => 'scheduled',
            'priority_tier' => 2, 'progress_mode' => 'derived', 'progress' => 0, 'version' => 1,
        ]);

        $goal = Goal::query()->create([
            'user_id' => $user->id, 'title' => 'Active goal', 'horizon' => 'custom',
            'status' => 'active', 'priority_tier' => 1, 'progress_mode' => 'derived', 'progress' => 50,
        ]);
        Milestone::query()->create([
            'user_id' => $user->id, 'goal_id' => $goal->id, 'title' => 'M1', 'sequence' => 1,
            'status' => 'completed', 'progress_mode' => 'derived', 'progress' => 100,
        ]);
        Goal::query()->create([
            'user_id' => $user->id, 'title' => 'Completed goal', 'horizon' => 'custom',
            'status' => 'completed', 'priority_tier' => 2, 'progress_mode' => 'derived', 'progress' => 100,
        ]);

        $program = Program::query()->create([
            'user_id' => $user->id, 'name' => 'KRS', 'workload_type' => 'flexible',
            'status' => 'active', 'priority_tier' => 1,
        ]);
        Task::query()->create([
            'user_id' => $user->id, 'program_id' => $program->id, 'goal_id' => $goal->id, 'title' => 'Program task',
            'status' => 'completed', 'priority_tier' => 3, 'progress_mode' => 'derived', 'progress' => 100, 'version' => 1,
        ]);

        $this->addFocusSession($user->id, '2026-08-18 10:00:00', 50);
        $this->addRecharge($user->id, '2026-08-19 10:25:00', 15);

        ActivityLog::query()->create([
            'user_id' => $user->id, 'event_type' => 'task_completed', 'entity_type' => 'task',
            'entity_id' => 1, 'title' => 'Done task', 'event_at' => '2026-08-18 09:30:00',
            'operation_id' => 'op-1', 'payload' => [],
        ]);
        ActivityLog::query()->create([
            'user_id' => $user->id, 'event_type' => 'task_started', 'entity_type' => 'task',
            'entity_id' => 1, 'title' => 'Done task', 'event_at' => '2026-08-18 10:05:00',
            'operation_id' => 'op-2', 'payload' => [],
        ]);

        ProgressEvent::query()->create([
            'user_id' => $user->id, 'event_type' => 'milestone_completed', 'entity_type' => 'milestone',
            'entity_id' => 1, 'title' => 'M1', 'occurred_at' => '2026-08-18 11:00:00',
            'operation_id' => 'op-3', 'payload' => [],
        ]);

        $this->withToken($token)->getJson('/api/v1/analytics/overview?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('from', '2026-08-18')
            ->assertJsonPath('to', '2026-08-19')
            ->assertJsonPath('task_completion.total_tasks', 3)
            ->assertJsonPath('task_completion.completed_tasks', 2)
            ->assertJsonPath('task_completion.completed_in_period', 1)
            ->assertJsonPath('task_completion.completion_rate', round(2 / 3, 4))
            ->assertJsonPath('task_completion.by_status.completed', 2)
            ->assertJsonPath('goal_progress.total_goals', 2)
            ->assertJsonPath('goal_progress.completed_goals', 1)
            ->assertJsonPath('goal_progress.total_milestones', 1)
            ->assertJsonPath('goal_progress.completed_milestones', 1)
            ->assertJsonPath('goal_progress.programs.0.tasks_completed', 1)
            ->assertJsonPath('goal_progress.programs.0.workload_completion', fn ($v) => (float) $v === 1.0)
            ->assertJsonPath('goal_progress.goals.0.deadline_health', 'no_deadline')
            ->assertJsonPath('goal_progress.deadline_health.completed', 1)
            ->assertJsonPath('goal_progress.goal_tasks_total', 1)
            ->assertJsonPath('goal_progress.goal_tasks_completed', 1)
            ->assertJsonPath('goal_progress.workload_completion', fn ($v) => (float) $v === 1.0)
            ->assertJsonPath('work_life.productive_minutes', 50)
            ->assertJsonPath('work_life.recharge_minutes', 15)
            ->assertJsonPath('activity.total_events', 2)
            ->assertJsonPath('activity.by_type.task_completed', 1)
            ->assertJsonPath('focus.total_sessions', 1)
            ->assertJsonPath('focus.total_minutes', 50)
            ->assertJsonPath('progress_events.total_events', 1)
            ->assertJsonPath('progress_events.by_type.milestone_completed', 1)
            ->assertJsonPath('capacity.days.0.date', '2026-08-18')
            ->assertJsonPath('capacity.days.0.scheduled_minutes', 0)
            ->assertJsonPath('capacity.days.0.status', 'ok')
            ->assertJsonPath('capacity.realization_ratio', fn ($v) => (float) $v === 0.0)
            ->assertJsonPath('capacity.recommendation', fn ($v) => in_array($v, ['MAINTAIN', 'REDUCE_LOAD', 'BOOST_AVAILABLE'], true));
    }

    public function test_overview_classifies_goal_deadline_health(): void
    {
        [$user, $token] = $this->userWithToken();

        Goal::query()->create([
            'user_id' => $user->id, 'title' => 'At risk', 'horizon' => 'custom',
            'start_date' => '2026-08-01', 'target_date' => '2026-08-30', 'status' => 'active',
            'priority_tier' => 1, 'progress_mode' => 'derived', 'progress' => 10,
        ]);
        Goal::query()->create([
            'user_id' => $user->id, 'title' => 'Overdue', 'horizon' => 'custom',
            'start_date' => '2026-07-01', 'target_date' => '2026-08-10', 'status' => 'active',
            'priority_tier' => 2, 'progress_mode' => 'derived', 'progress' => 50,
        ]);

        $this->withToken($token)->getJson('/api/v1/analytics/overview?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('goal_progress.deadline_health.at_risk', 1)
            ->assertJsonPath('goal_progress.deadline_health.overdue', 1)
            ->assertJsonPath('goal_progress.goals.0.deadline_health', 'at_risk')
            ->assertJsonPath('goal_progress.goals.1.deadline_health', 'overdue');
    }

    public function test_overview_reports_scheduled_load_and_realization(): void
    {
        [$user, $token] = $this->userWithToken();

        $task = Task::query()->create([
            'user_id' => $user->id, 'title' => 'Deep work', 'status' => 'scheduled',
            'priority_tier' => 1, 'progress_mode' => 'derived', 'progress' => 0, 'version' => 1,
        ]);
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: CarbonImmutable::parse('2026-08-18'),
            startAt: CarbonImmutable::parse('2026-08-18T09:00:00'),
            endAt: CarbonImmutable::parse('2026-08-18T10:30:00'),
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));
        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create(
                $user->id,
                'All day blocked',
                HardLandscapeType::oneTime(),
                '2026-08-18T00:00:00',
                '2026-08-19T00:00:00',
            ),
        );
        $this->addFocusSession($user->id, '2026-08-18 10:30:00', 60);

        $this->withToken($token)->getJson('/api/v1/analytics/overview?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('capacity.days.0.scheduled_minutes', 90)
            ->assertJsonPath('capacity.days.0.available_minutes', 0)
            ->assertJsonPath('capacity.days.0.overload_minutes', 90)
            ->assertJsonPath('capacity.days.0.status', 'overload')
            ->assertJsonPath('capacity.realization_ratio', fn ($v) => abs((float) $v - 60 / 90) < 0.0001);
    }

    public function test_overview_scopes_read_models_by_user(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        Task::query()->create([
            'user_id' => $other->id, 'title' => 'Not mine', 'status' => 'completed',
            'priority_tier' => 1, 'progress_mode' => 'derived', 'progress' => 100, 'version' => 1,
        ]);
        $this->addFocusSession($other->id, '2026-08-18 10:00:00', 400);

        $this->withToken($token)->getJson('/api/v1/analytics/overview?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('task_completion.total_tasks', 0)
            ->assertJsonPath('focus.total_minutes', 0);
    }

    public function test_pillars_aggregate_realization_vs_target(): void
    {
        [$user, $token] = $this->userWithToken();

        $program = Program::query()->create([
            'user_id' => $user->id, 'name' => 'Karier program', 'category' => 'karier',
            'workload_type' => 'structured', 'weekly_target_minutes' => 60,
            'status' => 'active', 'priority_tier' => 1,
        ]);
        $task = Task::query()->create([
            'user_id' => $user->id, 'program_id' => $program->id, 'title' => 'Career work',
            'status' => 'completed', 'priority_tier' => 1, 'progress_mode' => 'derived',
            'progress' => 100, 'estimated_minutes' => 120, 'version' => 1,
        ]);
        $uncategorized = Task::query()->create([
            'user_id' => $user->id, 'title' => 'No mapping', 'status' => 'completed',
            'priority_tier' => 2, 'progress_mode' => 'derived', 'progress' => 100,
            'estimated_minutes' => 45, 'version' => 1,
        ]);

        ProgressEvent::query()->create([
            'user_id' => $user->id, 'event_type' => 'task_completed', 'entity_type' => 'task',
            'entity_id' => $task->id, 'title' => 'Career work', 'occurred_at' => '2026-08-18 11:00:00',
            'operation_id' => 'op-pillar-1', 'payload' => [],
        ]);
        ProgressEvent::query()->create([
            'user_id' => $user->id, 'event_type' => 'task_completed', 'entity_type' => 'task',
            'entity_id' => $uncategorized->id, 'title' => 'No mapping', 'occurred_at' => '2026-08-18 12:00:00',
            'operation_id' => 'op-pillar-2', 'payload' => [],
        ]);

        $this->withToken($token)->getJson('/api/v1/analytics/pillars?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('pillars.0.key', 'karier')
            ->assertJsonPath('pillars.0.realization_minutes', 120)
            ->assertJsonPath('pillars.0.target_minutes', 60)
            ->assertJsonPath('pillars.0.percent', fn ($v) => abs((float) $v - 2.0) < 0.0001)
            ->assertJsonPath('pillars.4.key', 'uncategorized')
            ->assertJsonPath('pillars.4.realization_minutes', 45)
            ->assertJsonPath('pillars.4.target_minutes', 0)
            ->assertJsonPath('pillars.4.percent', null);
    }

    public function test_overview_includes_pillars(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->getJson('/api/v1/analytics/overview?from=2026-08-18&to=2026-08-19')
            ->assertOk()
            ->assertJsonPath('pillars.pillars.0.key', 'karier')
            ->assertJsonCount(5, 'pillars.pillars');
    }
}
