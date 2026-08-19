<?php

namespace Tests\Feature\Api;

use App\Domain\Scheduling\Contracts\HardLandscapeRepository;
use App\Domain\Scheduling\HardLandscapeEvent;
use App\Domain\Scheduling\ValueObjects\HardLandscapeType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HardLandscapeApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(): array
    {
        $user = User::factory()->create();
        $token = $user->createToken('owner')->plainTextToken;

        return [$user, $token];
    }

    private function createEvent(int $userId, string $title = 'Standup', string $start = '2026-08-19T09:00:00', string $end = '2026-08-19T09:30:00'): HardLandscapeEvent
    {
        return app(HardLandscapeRepository::class)->create(
            HardLandscapeEvent::create($userId, $title, HardLandscapeType::oneTime(), $start, $end),
        );
    }

    public function test_hard_landscape_requires_authentication(): void
    {
        $this->getJson('/api/v1/hard-landscape')->assertStatus(401);
        $this->postJson('/api/v1/hard-landscape', [])->assertStatus(401);
    }

    public function test_event_can_be_created(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/hard-landscape', [
            'title' => 'Standup',
            'type' => 'one_time',
            'start_at' => '2026-08-19T09:00:00',
            'end_at' => '2026-08-19T09:30:00',
        ])->assertStatus(201)
            ->assertJsonPath('hard_landscape.title', 'Standup')
            ->assertJsonPath('hard_landscape.type', 'one_time');
    }

    public function test_create_validates_input(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/hard-landscape', [
            'title' => '',
            'type' => 'bogus',
            'start_at' => '2026-08-19T10:00:00',
            'end_at' => '2026-08-19T09:00:00',
        ])->assertStatus(422);
    }

    public function test_create_rejects_overlap_with_409(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createEvent($user->id);

        $this->withToken($token)->postJson('/api/v1/hard-landscape', [
            'title' => 'Overlap',
            'type' => 'one_time',
            'start_at' => '2026-08-19T09:10:00',
            'end_at' => '2026-08-19T09:40:00',
        ])->assertStatus(409);
    }

    public function test_event_can_be_listed_and_fetched(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createEvent($user->id);

        $this->withToken($token)
            ->getJson('/api/v1/hard-landscape')
            ->assertStatus(200)
            ->assertJsonCount(1, 'hard_landscape');

        $event = app(HardLandscapeRepository::class)->listForUser($user->id)[0];

        $this->withToken($token)
            ->getJson("/api/v1/hard-landscape/{$event->id}")
            ->assertStatus(200)
            ->assertJsonPath('hard_landscape.title', 'Standup');
    }

    public function test_event_can_be_updated(): void
    {
        [$user, $token] = $this->userWithToken();
        $event = $this->createEvent($user->id);

        $this->withToken($token)
            ->patchJson("/api/v1/hard-landscape/{$event->id}", ['title' => 'Morning Standup'])
            ->assertStatus(200)
            ->assertJsonPath('hard_landscape.title', 'Morning Standup');
    }

    public function test_event_can_be_deleted(): void
    {
        [$user, $token] = $this->userWithToken();
        $event = $this->createEvent($user->id);

        $this->withToken($token)
            ->deleteJson("/api/v1/hard-landscape/{$event->id}")
            ->assertStatus(200)
            ->assertJsonPath('deleted', true);

        $this->assertCount(0, app(HardLandscapeRepository::class)->listForUser($user->id));
    }

    public function test_hard_landscape_is_scoped_to_owner(): void
    {
        [$owner, $token] = $this->userWithToken();
        $other = User::factory()->create();
        $event = $this->createEvent($other->id);

        $this->withToken($token)->getJson("/api/v1/hard-landscape/{$event->id}")->assertStatus(404);
        $this->withToken($token)->deleteJson("/api/v1/hard-landscape/{$event->id}")->assertStatus(404);
    }

    public function test_today_view_includes_hard_landscape(): void
    {
        [$user, $token] = $this->userWithToken();
        $this->createEvent($user->id, 'Standup', '2026-08-19T09:00:00', '2026-08-19T09:30:00');

        $this->withToken($token)
            ->getJson('/api/v1/today?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonCount(1, 'hard_landscape')
            ->assertJsonPath('hard_landscape.0.title', 'Standup');
    }
}
