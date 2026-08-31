<?php

namespace Tests\Feature\Scheduling;

use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ADR-016 §2.3 — reality-change trigger: bounded impact detection after
 * Hard Landscape mutations. Never auto-applies; flags review state once;
 * outside-window changes trigger nothing.
 */
final class ScheduleImpactTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlacement(): array
    {
        $user = User::factory()->create();
        $task = Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Placed work',
            'status' => 'scheduled',
            'priority_tier' => 2,
            'estimated_minutes' => 60,
            'progress_mode' => 'derived',
            'progress' => 0,
            'version' => 1,
        ]);

        $date = now()->toDateString();
        app(ScheduleAssignmentRepository::class)->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: $date,
            startAt: $date.' 10:00',
            endAt: $date.' 11:00',
            source: ScheduleAssignmentSource::draft(),
            scheduleVersion: 1,
        ));

        return [$user, $task];
    }

    private function createBlock(int $userId, string $start, string $end): void
    {
        // Through the API so the impact wiring under test runs.
        $user = User::query()->findOrFail($userId);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/hard-landscape', [
            'title' => 'New reality',
            'type' => 'one_time',
            'start_at' => $start,
            'end_at' => $end,
        ])->assertStatus(201);
    }

    public function test_overlapping_reality_change_flags_needs_review(): void
    {
        [$user] = $this->userWithPlacement();
        $date = now()->toDateString();

        $this->createBlock($user->id, $date.' 10:30', $date.' 12:00');

        $today = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/today?date='.$date);
        $today->assertStatus(200)->assertJsonPath('schedule_needs_review', true);

        // One attention notification (no spam on repeated changes).
        $this->createBlock($user->id, $date.' 13:00', $date.' 14:00');
        $notifications = app(NotificationRepository::class)->listForUser($user->id);
        $review = array_values(array_filter(
            $notifications,
            static fn ($n) => $n->type->equals(NotificationType::scheduleNeedsReview()),
        ));
        $this->assertCount(1, $review);
    }

    public function test_non_overlapping_reality_change_does_not_flag(): void
    {
        [$user] = $this->userWithPlacement();
        $date = now()->toDateString();

        $this->createBlock($user->id, $date.' 15:00', $date.' 16:00');

        $today = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/today?date='.$date);
        $today->assertStatus(200)->assertJsonPath('schedule_needs_review', false);
    }

    public function test_far_future_change_does_not_trigger_work(): void
    {
        [$user] = $this->userWithPlacement();

        $this->createBlock($user->id, now()->addDays(60)->toDateString().' 10:00', now()->addDays(60)->toDateString().' 11:00');

        $today = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/today?date='.now()->toDateString());
        $today->assertStatus(200)->assertJsonPath('schedule_needs_review', false);
        $this->assertCount(0, app(NotificationRepository::class)->listForUser($user->id));
    }

    public function test_manual_placements_are_not_flagged(): void
    {
        [$user, $task] = $this->userWithPlacement();

        // Replace the auto-sourced placement with a manual one.
        $repo = app(ScheduleAssignmentRepository::class);
        $placement = $repo->listForTask($task->id)[0];
        $repo->deleteForUser($user->id, $placement->id);
        $repo->create(ScheduleAssignment::create(
            userId: $user->id,
            taskId: $task->id,
            date: now()->toDateString(),
            startAt: now()->toDateString().' 10:00',
            endAt: now()->toDateString().' 11:00',
            source: ScheduleAssignmentSource::manual(),
            scheduleVersion: 2,
        ));

        $this->createBlock($user->id, now()->toDateString().' 10:30', now()->toDateString().' 12:00');

        $today = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/today?date='.now()->toDateString());
        $today->assertStatus(200)->assertJsonPath('schedule_needs_review', false);
    }

    public function test_hard_landscape_mutation_never_fails_from_impact_detection(): void
    {
        // The authoritative mutation succeeds even when the impact path is
        // broken (ADR-016 §2.8 failure isolation): overlapping placement +
        // valid block → 201 regardless.
        [$user] = $this->userWithPlacement();
        $date = now()->toDateString();

        $this->createBlock($user->id, $date.' 10:00', $date.' 11:00');

        $this->assertDatabaseCount('hard_landscape_events', 1);
    }
}
