<?php

namespace Tests\Feature\Console;

use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Models\BreakPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakEndNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_one_notification_for_break_ending_in_three_days(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-18T09:00:00'));

        $this->artisan('break:notify-end')->assertSuccessful();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::BREAK_END,
            'scheduled_for' => '2026-08-18',
        ]);
    }

    public function test_command_is_idempotent_on_retry(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-18T09:00:00'));

        $this->artisan('break:notify-end')->assertSuccessful();
        $this->artisan('break:notify-end')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_command_skips_breaks_not_ending_in_three_days(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-25',
            'status' => 'active',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-18T09:00:00'));

        $this->artisan('break:notify-end')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_command_ignores_ended_breaks(): void
    {
        $user = User::factory()->create();
        BreakPeriod::query()->create([
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'ended',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-18T09:00:00'));

        $this->artisan('break:notify-end')->assertSuccessful();

        $this->assertDatabaseCount('notifications', 0);
    }
}
