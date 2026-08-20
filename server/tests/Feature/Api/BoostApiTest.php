<?php

namespace Tests\Feature\Api;

use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Models\BoostTarget;
use App\Models\BreakPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_boost_requires_authentication(): void
    {
        $this->getJson('/api/v1/boost')->assertUnauthorized();
        $this->postJson('/api/v1/boost', ['target_percent' => 60])->assertUnauthorized();
    }

    public function test_setup_is_not_eligible_without_active_break(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/boost')
            ->assertOk()
            ->assertJsonPath('eligible', false)
            ->assertJsonPath('recommendation.recommendation', 'NOT_ELIGIBLE')
            ->assertJsonPath('safety_cap_percent', 70);
    }

    public function test_setup_is_eligible_with_active_break(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson('/api/v1/boost')
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('break_start_date', '2026-08-17')
            ->assertJsonPath('break_end_date', '2026-08-21')
            ->assertJsonPath('active_target', null);
    }

    public function test_set_target_requires_active_break(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/boost', ['target_percent' => 60])
            ->assertStatus(422);
    }

    public function test_set_target_caps_above_safety_limit_with_warning(): void
    {
        $user = User::factory()->create();
        $period = BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/boost', [
            'target_percent' => 90,
            'break_period_id' => $period->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('capped', true)
            ->assertJsonPath('target.target_percent', 70)
            ->assertJsonPath('target.status', 'active')
            ->assertJsonPath('target.break_period_id', $period->id)
            ->assertJsonPath('warning', fn ($value) => str_contains($value, 'safety cap'));

        $this->assertDatabaseHas('boost_targets', [
            'user_id' => $user->id,
            'target_percent' => 70,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BOOST_START,
        ]);
    }

    public function test_set_target_within_cap_is_saved_without_warning(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/boost', [
            'target_percent' => 60,
        ]);

        $response->assertOk()
            ->assertJsonPath('capped', false)
            ->assertJsonPath('warning', null)
            ->assertJsonPath('target.target_percent', 60)
            ->assertJsonPath('target.start_date', '2026-08-17')
            ->assertJsonPath('target.end_date', '2026-08-21');
    }

    public function test_set_target_rejects_invalid_percent_and_out_of_break_range(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $this->actingAs($user)->postJson('/api/v1/boost', ['target_percent' => 0])
            ->assertStatus(422);

        $this->actingAs($user)->postJson('/api/v1/boost', [
            'target_percent' => 60,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
        ])->assertStatus(422);
    }

    public function test_saving_new_target_ends_previous_active_target(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $this->actingAs($user)->postJson('/api/v1/boost', ['target_percent' => 50])->assertOk();
        $this->actingAs($user)->postJson('/api/v1/boost', ['target_percent' => 65])->assertOk();

        $this->assertSame(1, BoostTarget::query()->where('user_id', $user->id)->where('status', 'active')->count());
        $this->assertSame(1, BoostTarget::query()->where('user_id', $user->id)->where('status', 'ended')->count());
    }

    public function test_end_boost_target_returns_to_baseline(): void
    {
        $user = User::factory()->create();
        $period = BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);
        $target = BoostTarget::query()->create([
            'user_id' => $user->id,
            'break_period_id' => $period->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'target_percent' => 60,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/boost/end');

        $response->assertOk()
            ->assertJsonPath('applied', true)
            ->assertJsonPath('target_id', $target->id);

        $this->assertDatabaseHas('boost_targets', [
            'id' => $target->id,
            'status' => 'ended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BOOST_END,
        ]);
    }

    public function test_end_boost_target_without_active_target_is_noop(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/boost/end')
            ->assertStatus(202)
            ->assertJsonPath('applied', false);
    }

    public function test_effective_boost_target_reported_through_setup(): void
    {
        $user = User::factory()->create();
        $period = BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);
        BoostTarget::query()->create([
            'user_id' => $user->id,
            'break_period_id' => $period->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'target_percent' => 60,
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson('/api/v1/boost')
            ->assertOk()
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('active_target.target_percent', 60);
    }
}
