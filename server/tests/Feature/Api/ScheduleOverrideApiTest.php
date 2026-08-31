<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleAssignmentRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ScheduleAssignment;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Domain\Scheduling\ValueObjects\ScheduleAssignmentSource;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleOverrideApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createSource(int $userId): int
    {
        return app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create(
                $userId,
                'Daily Standup',
                HardLandscapeType::recurring(),
                '2026-08-17T09:00:00',
                '2026-08-17T09:30:00',
                'FREQ=DAILY',
            ),
        )->id;
    }

    private function payload(int $sourceId, string $date = '2026-08-19'): array
    {
        return [
            'hard_landscape_event_id' => $sourceId,
            'type' => 'one_time',
            'effective_from' => "{$date}T09:00:00",
            'effective_to' => "{$date}T09:00:00",
            'override_start_at' => "{$date}T14:00:00",
            'override_end_at' => "{$date}T14:30:00",
            'reason' => 'Moved',
        ];
    }

    public function test_overrides_require_authentication(): void
    {
        $this->getJson('/api/v1/schedule-overrides')->assertStatus(401);
        $this->postJson('/api/v1/schedule-overrides', [])->assertStatus(401);
    }

    public function test_override_can_be_created(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->createSource($user->id);

        $this->withToken($token)
            ->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))
            ->assertStatus(201)
            ->assertJsonPath('override.type', 'one_time')
            ->assertJsonPath('override.hard_landscape_event_id', $sourceId);
    }

    public function test_create_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/schedule-overrides', [
            'type' => 'bogus',
            'effective_from' => '2026-08-19T09:00:00',
            'effective_to' => '2026-08-19T09:00:00',
            'override_start_at' => '2026-08-19T14:00:00',
            'override_end_at' => '2026-08-19T13:00:00',
        ])->assertStatus(422);
    }

    public function test_create_rejects_overlap_with_409(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->createSource($user->id);
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))->assertStatus(201);

        $overlap = $this->payload($sourceId, '2026-08-19');
        $overlap['override_start_at'] = '2026-08-19T14:15:00';
        $overlap['override_end_at'] = '2026-08-19T14:45:00';

        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $overlap)->assertStatus(409);
    }

    public function test_override_can_be_listed_and_fetched(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->createSource($user->id);
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))->assertStatus(201);

        $this->withToken($token)
            ->getJson('/api/v1/schedule-overrides')
            ->assertStatus(200)
            ->assertJsonCount(1, 'overrides');

        $override = app(ScheduleOverrideRepository::class)->listForUser($user->id)[0];

        $this->withToken($token)
            ->getJson("/api/v1/schedule-overrides/{$override->id}")
            ->assertStatus(200)
            ->assertJsonPath('override.reason', 'Moved');
    }

    public function test_override_can_be_updated(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->createSource($user->id);
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))->assertStatus(201);

        $override = app(ScheduleOverrideRepository::class)->listForUser($user->id)[0];

        $this->withToken($token)
            ->patchJson("/api/v1/schedule-overrides/{$override->id}", ['reason' => 'Updated reason'])
            ->assertStatus(200)
            ->assertJsonPath('override.reason', 'Updated reason');
    }

    public function test_override_can_be_deleted(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->createSource($user->id);
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))->assertStatus(201);

        $override = app(ScheduleOverrideRepository::class)->listForUser($user->id)[0];

        $this->withToken($token)
            ->deleteJson("/api/v1/schedule-overrides/{$override->id}")
            ->assertStatus(200)
            ->assertJsonPath('deleted', true);

        $this->assertCount(0, app(ScheduleOverrideRepository::class)->listForUser($user->id));
    }

    public function test_overrides_are_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $sourceId = $this->createSource($other->id);
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', $this->payload($sourceId))->assertStatus(404);

        $this->withToken($token)->getJson('/api/v1/schedule-overrides')->assertStatus(200)->assertJsonCount(0, 'overrides');
    }

    // ------------------------------------------------------------------
    // ES-IMPL-04/05 — overrides change the effective schedule (ADR-015)
    // ------------------------------------------------------------------

    private function recurringSource(int $userId, string $start = '2026-09-07T09:00:00', string $end = '2026-09-07T10:00:00', string $recurrence = 'FREQ=WEEKLY'): int
    {
        return app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, 'KRS: Algorithms', HardLandscapeType::recurring(), $start, $end, $recurrence),
        )->id;
    }

    public function test_permanent_shift_reaches_today_on_covered_dates(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->recurringSource($user->id); // Mondays 09:00.

        $this->withToken($token)->postJson('/api/v1/schedule-overrides', [
            'hard_landscape_event_id' => $sourceId,
            'type' => 'permanent',
            'effective_from' => '2026-09-14T00:00:00',
            'effective_to' => '2026-12-31T00:00:00',
            'override_start_at' => '2026-09-16T13:00:00',
            'override_end_at' => '2026-09-16T14:00:00',
            'reason' => 'Room change',
        ])->assertStatus(201);

        // The pre-shift Monday is VACATED (its block moved to Wednesday).
        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-09-07')
            ->assertStatus(200)
            ->assertJsonCount(1, 'hard_landscape')
            ->assertJsonPath('hard_landscape.0.provenance', 'base'); // 09-07 is before the shift boundary.

        // The first covered Monday (09-14) is vacated…
        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-09-14')
            ->assertStatus(200)
            ->assertJsonCount(0, 'hard_landscape');

        // …and its effective occurrence appears on the shifted Wednesday.
        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-09-16')
            ->assertStatus(200)
            ->assertJsonCount(1, 'hard_landscape')
            ->assertJsonPath('hard_landscape.0.start_at', '2026-09-16T13:00:00.000000Z')
            ->assertJsonPath('hard_landscape.0.provenance', fn ($p) => str_starts_with((string) $p, 'shifted:'))
            ->assertJsonPath('hard_landscape.0.original_start', '2026-09-14T09:00:00.000000Z');
    }

    public function test_cancelling_one_time_exception_removes_the_target_occurrence_from_today(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->recurringSource($user->id, '2026-08-17T09:00:00', '2026-08-17T10:00:00');

        $this->withToken($token)->postJson('/api/v1/schedule-overrides', [
            'hard_landscape_event_id' => $sourceId,
            'type' => 'one_time',
            'effective_from' => '2026-08-24T00:00:00',
            'effective_to' => '2026-08-24T00:00:00',
            'override_start_at' => '2026-08-24T09:00:00',
            'override_end_at' => '2026-08-24T10:00:00',
            'reason' => 'Public holiday',
            'cancels_occurrence' => true,
        ])->assertStatus(201);

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-24')
            ->assertStatus(200)
            ->assertJsonCount(0, 'hard_landscape');

        // Adjacent occurrences are untouched.
        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-31')
            ->assertStatus(200)
            ->assertJsonCount(1, 'hard_landscape');
    }

    public function test_scheduler_respects_the_shifted_interval(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceId = $this->recurringSource($user->id);

        $this->withToken($token)->postJson('/api/v1/schedule-overrides', [
            'hard_landscape_event_id' => $sourceId,
            'type' => 'permanent',
            'effective_from' => '2026-09-14T00:00:00',
            'effective_to' => '2026-12-31T00:00:00',
            'override_start_at' => '2026-09-16T13:00:00',
            'override_end_at' => '2026-09-16T14:00:00',
        ])->assertStatus(201);

        app(ScheduleAssignmentRepository::class)->create(
            ScheduleAssignment::create(
                userId: $user->id,
                taskId: Task::query()->create([
                    'user_id' => $user->id,
                    'title' => 'Deep work',
                    'status' => 'backlog',
                    'priority_tier' => 3,
                    'progress_mode' => 'derived',
                    'progress' => 0,
                    'version' => 1,
                    'estimated_minutes' => 60,
                ])->id,
                date: '2026-09-16',
                startAt: '2026-09-16T13:30:00',
                endAt: '2026-09-16T14:30:00',
                source: ScheduleAssignmentSource::draft(),
                scheduleVersion: 1,
            ),
        );

        $proposal = $this->withToken($token)
            ->postJson('/api/v1/schedule/reschedule', ['from' => '2026-09-16', 'to' => '2026-09-16'])
            ->assertStatus(200)
            ->json()['proposal'];

        $this->assertNotEmpty($proposal['moves'], 'Work overlapping the shifted block must be re-proposed.');
    }

    public function test_shift_colliding_with_another_landscape_is_rejected_with_409(): void
    {
        [$user, $token] = $this->userWithToken();
        $sourceA = $this->recurringSource($user->id); // Mondays 09:00–10:00.
        app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create(
                $user->id,
                'Gym',
                HardLandscapeType::recurring(),
                '2026-09-18T13:00:00',
                '2026-09-18T14:00:00',
                'FREQ=WEEKLY',
            ),
        ); // Fridays 13:00–14:00.

        // Shifting source A onto the Gym window must be rejected: the first
        // covered Monday (09-14) re-times to Friday 09-18 13:30–14:30, which
        // collides with the Gym's Friday 13:00–14:00 block.
        $this->withToken($token)->postJson('/api/v1/schedule-overrides', [
            'hard_landscape_event_id' => $sourceA,
            'type' => 'permanent',
            'effective_from' => '2026-09-14T00:00:00',
            'effective_to' => '2026-12-31T00:00:00',
            'override_start_at' => '2026-09-18T13:30:00',
            'override_end_at' => '2026-09-18T14:30:00',
        ])->assertStatus(409);
    }
}
