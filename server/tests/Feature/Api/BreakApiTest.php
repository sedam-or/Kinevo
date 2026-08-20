<?php

namespace Tests\Feature\Api;

use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Models\BreakPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_break_requires_authentication(): void
    {
        $this->postJson('/api/v1/break', [
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
        ])->assertUnauthorized();
    }

    public function test_start_break_confirms_period_and_returns_summary(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/break', [
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
        ]);

        $periodId = BreakPeriod::query()->where('user_id', $user->id)->value('id');

        $response->assertOk()
            ->assertJsonPath('break_period_id', $periodId)
            ->assertJsonPath('start_date', '2026-08-17')
            ->assertJsonPath('end_date', '2026-08-21')
            ->assertJsonPath('explanation', fn ($value) => str_contains($value, 'Break Mode confirmed'));

        $this->assertDatabaseHas('break_periods', [
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BREAK_START,
        ]);
    }

    public function test_start_break_rejects_invalid_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/break', [
            'start_date' => '2026-08-21',
            'end_date' => '2026-08-17',
        ])->assertStatus(422);
    }

    public function test_start_break_rejects_missing_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/break', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_end_break_ends_active_period(): void
    {
        $user = User::factory()->create();
        $period = BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/break/end');

        $response->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('break_period_id', $period->id)
            ->assertJsonPath('duration_days', 5);

        $this->assertDatabaseHas('break_periods', [
            'id' => $period->id,
            'status' => 'ended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BREAK_END,
        ]);
    }

    public function test_end_break_without_active_period_is_noop(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/break/end')
            ->assertStatus(202)
            ->assertJsonPath('applied', false);
    }

    public function test_today_reports_active_break_state(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson('/api/v1/today?date=2026-08-19')
            ->assertOk()
            ->assertJsonPath('break.start_date', '2026-08-17')
            ->assertJsonPath('break.end_date', '2026-08-21')
            ->assertJsonPath('break.status', 'active');
    }

    public function test_today_reports_null_break_outside_period(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/today?date=2026-08-19')
            ->assertOk()
            ->assertJsonPath('break', null);
    }
}
