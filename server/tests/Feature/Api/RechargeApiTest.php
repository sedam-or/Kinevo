<?php

namespace Tests\Feature\Api;

use App\Models\FocusSession;
use App\Models\RechargeSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RechargeApiTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_recharge_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/recharge/status')->assertStatus(401);
        $this->getJson('/api/v1/recharge')->assertStatus(401);
        $this->postJson('/api/v1/recharge/start')->assertStatus(401);
    }

    public function test_status_reports_no_cue_without_two_focus_sessions(): void
    {
        [$user, $token] = $this->userWithToken();
        Carbon::setTestNow('2026-08-20 12:00:00');

        $this->addFocusSession($user->id, '2026-08-20 09:25:00');

        $this->withToken($token)->getJson('/api/v1/recharge/status')
            ->assertStatus(200)
            ->assertJsonPath('recharge', null)
            ->assertJsonPath('cue_available', false)
            ->assertJsonPath('completed_focus_today', 1)
            ->assertJsonPath('due_recharges', 0);
    }

    public function test_second_focus_session_enables_the_recharge_cue(): void
    {
        [$user, $token] = $this->userWithToken();
        Carbon::setTestNow('2026-08-20 12:00:00');

        $this->addFocusSession($user->id, '2026-08-20 09:25:00');
        $this->addFocusSession($user->id, '2026-08-20 10:25:00');

        $this->withToken($token)->getJson('/api/v1/recharge/status')
            ->assertStatus(200)
            ->assertJsonPath('cue_available', true)
            ->assertJsonPath('completed_focus_today', 2)
            ->assertJsonPath('due_recharges', 1)
            ->assertJsonPath('completed_recharges_today', 0);
    }

    public function test_recharge_timer_can_start_pause_resume_and_complete(): void
    {
        [$user, $token] = $this->userWithToken();
        Carbon::setTestNow('2026-08-20 10:00:00');

        $started = $this->withToken($token)->postJson('/api/v1/recharge/start')
            ->assertStatus(201)
            ->assertJsonPath('recharge.status', 'running')
            ->assertJsonPath('recharge.duration_minutes', null);

        $sessionId = $started->json('recharge.id');

        $this->assertDatabaseHas('recharge_sessions', ['id' => $sessionId, 'status' => 'running']);

        Carbon::setTestNow('2026-08-20 10:05:00');

        $paused = $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/pause")
            ->assertStatus(200)
            ->assertJsonPath('recharge.status', 'paused')
            ->assertJsonPath('recharge.accumulated_seconds', 300);

        $resumed = $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/resume")
            ->assertStatus(200)
            ->assertJsonPath('recharge.status', 'running');

        Carbon::setTestNow('2026-08-20 10:20:00');

        $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('recharge.status', 'completed')
            ->assertJsonPath('recharge.duration_minutes', 20);

        $this->assertDatabaseHas('recharge_sessions', [
            'id' => $sessionId,
            'status' => 'completed',
            'duration_minutes' => 20,
        ]);

        $this->assertNotSame($paused->json('recharge.accumulated_seconds'), 0);
        $this->assertSame(300, $paused->json('recharge.accumulated_seconds'));
    }

    public function test_completed_recharge_contributes_to_recharge_minutes_and_ratio(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->addFocusSession($user->id, '2026-08-20 09:25:00');
        $this->addFocusSession($user->id, '2026-08-20 10:25:00');

        Carbon::setTestNow('2026-08-20 09:30:00');

        $started = $this->withToken($token)->postJson('/api/v1/recharge/start');
        $sessionId = $started->json('recharge.id');

        Carbon::setTestNow('2026-08-20 09:45:00');

        $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/complete")
            ->assertStatus(200)
            ->assertJsonPath('recharge.duration_minutes', 15);

        // 50 productive minutes + 15 recharge minutes → WorkRatio 50/65, Recharge 15/65.
        $this->withToken($token)->getJson('/api/v1/recharge/status')
            ->assertStatus(200)
            ->assertJsonPath('recharge_minutes_today', 15)
            ->assertJsonPath('productive_minutes_today', 50)
            ->assertJsonPath('work_ratio', 0.7692)
            ->assertJsonPath('recharge_ratio', 0.2308)
            ->assertJsonPath('completed_recharges_today', 1)
            ->assertJsonPath('cue_available', false);
    }

    public function test_abandoning_recharge_records_no_duration(): void
    {
        [$user, $token] = $this->userWithToken();
        Carbon::setTestNow('2026-08-20 10:00:00');

        $started = $this->withToken($token)->postJson('/api/v1/recharge/start');
        $sessionId = $started->json('recharge.id');

        Carbon::setTestNow('2026-08-20 10:03:00');

        $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/abandon")
            ->assertStatus(200)
            ->assertJsonPath('recharge.status', 'abandoned')
            ->assertJsonPath('recharge.duration_minutes', null);

        $this->assertDatabaseHas('recharge_sessions', ['id' => $sessionId, 'status' => 'abandoned']);
    }

    public function test_start_rejects_an_already_active_recharge(): void
    {
        [$user, $token] = $this->userWithToken();

        $this->withToken($token)->postJson('/api/v1/recharge/start')->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/recharge/start')->assertStatus(409);
    }

    public function test_sessions_are_scoped_to_owner(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        $started = $this->withToken($token)->postJson('/api/v1/recharge/start');
        $sessionId = $started->json('recharge.id');

        $otherToken = $other->createToken('owner')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->postJson("/api/v1/recharge/{$sessionId}/complete")
            ->assertStatus(404);
    }

    public function test_status_is_scoped_to_the_requested_day(): void
    {
        [$user, $token] = $this->userWithToken();
        Carbon::setTestNow('2026-08-20 12:00:00');

        // Two sessions on a previous day must not enable today's cue.
        $this->addFocusSession($user->id, '2026-08-19 09:25:00');
        $this->addFocusSession($user->id, '2026-08-19 10:25:00');

        $this->withToken($token)->getJson('/api/v1/recharge/status?date=2026-08-20')
            ->assertStatus(200)
            ->assertJsonPath('completed_focus_today', 0)
            ->assertJsonPath('cue_available', false);

        $this->withToken($token)->getJson('/api/v1/recharge/status?date=2026-08-19')
            ->assertStatus(200)
            ->assertJsonPath('completed_focus_today', 2)
            ->assertJsonPath('cue_available', true);
    }

    public function test_recharge_sessions_list_is_ordered_and_scoped(): void
    {
        [$user, $token] = $this->userWithToken();
        $other = User::factory()->create();

        Carbon::setTestNow('2026-08-20 10:00:00');
        $started = $this->withToken($token)->postJson('/api/v1/recharge/start');
        $sessionId = $started->json('recharge.id');

        Carbon::setTestNow('2026-08-20 10:10:00');
        $this->withToken($token)->postJson("/api/v1/recharge/{$sessionId}/complete");

        RechargeSession::query()->create([
            'user_id' => $other->id,
            'status' => 'completed',
            'started_at' => '2026-08-20 09:00:00',
            'last_resumed_at' => null,
            'accumulated_seconds' => 600,
            'duration_minutes' => 10,
            'ended_at' => '2026-08-20 09:10:00',
        ]);

        $this->withToken($token)->getJson('/api/v1/recharge')
            ->assertStatus(200)
            ->assertJsonCount(1, 'recharges')
            ->assertJsonPath('recharges.0.id', $sessionId);
    }
}
