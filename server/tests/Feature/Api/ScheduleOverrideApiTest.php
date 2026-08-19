<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\Contracts\ScheduleOverrideRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
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
}
