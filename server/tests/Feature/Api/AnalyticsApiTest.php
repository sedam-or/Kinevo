<?php

namespace Tests\Feature\Api;

use App\Models\FocusSession;
use App\Models\RechargeSession;
use App\Models\User;
use Carbon\Carbon;
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
}
