<?php

namespace Tests\Feature\Console;

use App\Models\PauseEvent;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EodReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createTask(int $userId, array $overrides = []): Task
    {
        return Task::query()->create([
            'user_id' => $userId,
            'title' => $overrides['title'] ?? 'Default task',
            'status' => $overrides['status'] ?? 'backlog',
            'priority_tier' => $overrides['priority_tier'] ?? 3,
            'progress_mode' => 'derived',
            'progress' => 0,
        ]);
    }

    public function test_prompt_phase_creates_one_reconciliation_notification(): void
    {
        $user = User::factory()->create();
        $this->createTask($user->id, ['status' => 'scheduled', 'title' => 'Ship feature']);

        $this->artisan('eod:reconcile', ['--phase' => 'prompt'])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'reconciliation',
        ]);
    }

    public function test_prompt_phase_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create();
        $this->createTask($user->id, ['status' => 'scheduled']);

        $this->artisan('eod:reconcile', ['--phase' => 'prompt'])->assertSuccessful();
        $this->artisan('eod:reconcile', ['--phase' => 'prompt'])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_prompt_phase_creates_no_notification_without_untouched_tasks(): void
    {
        $user = User::factory()->create();
        $this->createTask($user->id, ['status' => 'backlog', 'title' => 'Not scheduled']);
        $this->createTask($user->id, ['status' => 'completed', 'title' => 'Already done']);

        $this->artisan('eod:reconcile', ['--phase' => 'prompt'])->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_prompt_phase_is_suppressed_during_emergency_pause_week(): void
    {
        $user = User::factory()->create();
        $this->createTask($user->id, ['status' => 'scheduled', 'title' => 'Ship feature']);

        // Tag the current week as exceptional (Emergency Pause, FR-07).
        $now = CarbonImmutable::now();
        PauseEvent::query()->create([
            'user_id' => $user->id,
            'type' => 'emergency',
            'week_start' => $now->startOfWeek()->toDateString(),
            'week_end' => $now->startOfWeek()->addDays(6)->toDateString(),
            'keep_task_ids' => [],
            'moved_task_ids' => [],
            'conflict_task_ids' => [],
            'schedule_version' => 1,
        ]);

        $this->artisan('eod:reconcile', ['--phase' => 'prompt'])->assertSuccessful();

        // FR-47: emergency pause suppression preserves audit data but creates
        // no reconciliation notification during the exceptional week.
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_deadline_phase_marks_scheduled_tasks_missed(): void
    {
        $user = User::factory()->create();
        $scheduled = $this->createTask($user->id, ['status' => 'scheduled', 'title' => 'Ship feature']);
        $completed = $this->createTask($user->id, ['status' => 'completed', 'title' => 'Already done']);

        $this->artisan('eod:reconcile', ['--phase' => 'deadline'])->assertSuccessful();

        $this->assertDatabaseHas('tasks', ['id' => $scheduled->id, 'status' => 'missed']);
        $this->assertDatabaseHas('tasks', ['id' => $completed->id, 'status' => 'completed']);
    }

    public function test_deadline_phase_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create();
        $scheduled = $this->createTask($user->id, ['status' => 'scheduled']);

        $this->artisan('eod:reconcile', ['--phase' => 'deadline'])->assertSuccessful();
        $this->artisan('eod:reconcile', ['--phase' => 'deadline'])->assertSuccessful();

        $this->assertDatabaseHas('tasks', ['id' => $scheduled->id, 'status' => 'missed']);
    }
}
