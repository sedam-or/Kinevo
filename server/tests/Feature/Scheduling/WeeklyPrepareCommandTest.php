<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleDraftRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Domain\Scheduling\ValueObjects\ScheduleDraftStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * ADR-016 §2.1 — weekly planning trigger contract: persisted pending draft,
 * never auto-applied, idempotent per week anchor, stale refresh, user
 * isolation. The command accepts --user to force a pass for one user
 * (deterministic regardless of the host's weekday).
 */
final class WeeklyPrepareCommandTest extends TestCase
{
    use RefreshDatabase;

    private function userWithTask(array $taskOverrides = []): array
    {
        $user = User::factory()->create();
        $task = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Flexible work',
            'status' => 'backlog',
            'priority_tier' => 2,
            'estimated_minutes' => $taskOverrides['estimated_minutes'] ?? 60,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
            'is_sacred_anchor' => $taskOverrides['is_sacred_anchor'] ?? false,
        ]);

        return [$user, $task];
    }

    public function test_prepared_draft_is_pending_and_never_auto_applied(): void
    {
        [$user, $task] = $this->userWithTask();

        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $drafts = app(ScheduleDraftRepository::class)->listPendingForUser($user->id);
        $this->assertCount(1, $drafts);
        $this->assertSame('weekly', $drafts[0]->source);
        $this->assertSame('pending', $drafts[0]->status->value);
        $this->assertSame(1, $drafts[0]->baseVersion);

        // The accepted schedule is untouched: no assignment exists for the task.
        $this->assertSame(
            [],
            app(ScheduleAssignmentRepository::class)->listForTask($task->id),
        );

        // Weekly-draft-ready notification exists exactly once.
        $notifications = app(NotificationRepository::class)->listForUser($user->id);
        $weekly = array_values(array_filter(
            $notifications,
            static fn ($n) => $n->type->equals(NotificationType::weeklyDraftReady()),
        ));
        $this->assertCount(1, $weekly);
    }

    public function test_duplicate_run_is_idempotent(): void
    {
        [$user] = $this->userWithTask();

        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);
        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $this->assertCount(1, app(ScheduleDraftRepository::class)->listPendingForUser($user->id));
        $notifications = app(NotificationRepository::class)->listForUser($user->id);
        $weekly = array_values(array_filter(
            $notifications,
            static fn ($n) => $n->type->equals(NotificationType::weeklyDraftReady()),
        ));
        $this->assertCount(1, $weekly);
    }

    public function test_user_without_schedulable_tasks_gets_no_draft(): void
    {
        $user = User::factory()->create();

        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $this->assertCount(0, app(ScheduleDraftRepository::class)->listPendingForUser($user->id));
    }

    public function test_stale_pending_draft_is_refreshed_in_place(): void
    {
        [$user, $task] = $this->userWithTask();
        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $drafts = app(ScheduleDraftRepository::class);
        $original = $drafts->listPendingForUser($user->id)[0];
        $this->assertSame(1, $original->baseVersion);

        // Reality moved on: the user applied a quick capture at version 2.
        app(ScheduleAssignmentRepository::class)->create(
            ScheduleAssignment::create(
                userId: $user->id,
                taskId: $task->id,
                date: now()->toDateString(),
                startAt: now()->setTime(8, 0),
                endAt: now()->setTime(9, 0),
                source: ScheduleAssignmentSource::quickCapture(),
                scheduleVersion: 2,
            ),
        );

        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $pending = $drafts->listPendingForUser($user->id);
        $this->assertCount(1, $pending);
        $this->assertSame($original->id, $pending[0]->id, 'refresh happens in place (one row per week)');
        $this->assertSame(2, $pending[0]->baseVersion, 'stale draft is regenerated against the current version');
    }

    public function test_applied_week_is_not_regenerated(): void
    {
        [$user] = $this->userWithTask();
        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $drafts = app(ScheduleDraftRepository::class);
        $draft = $drafts->listPendingForUser($user->id)[0];
        $drafts->updateStatus($user->id, (int) $draft->id, ScheduleDraftStatus::applied());

        Artisan::call('schedule:prepare-weekly', ['--user' => $user->id]);

        $this->assertCount(0, $drafts->listPendingForUser($user->id));
        $kept = $drafts->findWeeklyForWeek($user->id, $draft->generatedForWeek ?? now()->startOfWeek());
        $this->assertNotNull($kept);
        $this->assertSame('applied', $kept->status->value);
    }

    public function test_drafts_are_owner_scoped(): void
    {
        [$userA] = $this->userWithTask();
        [$userB] = $this->userWithTask();

        Artisan::call('schedule:prepare-weekly', ['--user' => $userA->id]);

        $this->assertCount(0, app(ScheduleDraftRepository::class)->listPendingForUser($userB->id));
    }
}
