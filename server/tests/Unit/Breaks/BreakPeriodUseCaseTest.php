<?php

namespace Tests\Unit\Breaks;

use App\Application\ActivityLogs\RecordActivityUseCase;
use App\Application\Breaks\EndBreakUseCase;
use App\Application\Breaks\RunBreakEndNotificationUseCase;
use App\Application\Breaks\StartBreakUseCase;
use App\Domain\ActivityLogs\ValueObjects\ActivityEventType;
use App\Domain\Breaks\BreakPeriod;
use App\Domain\Breaks\Contracts\BreakPeriodRepository;
use App\Domain\Breaks\ValueObjects\BreakPeriodStatus;
use App\Domain\Notifications\Contracts\NotificationRepository;
use App\Domain\Notifications\Notification;
use App\Domain\Notifications\ValueObjects\NotificationType;
use App\Infrastructure\Breaks\EloquentBreakPeriodRepository;
use App\Infrastructure\Notifications\EloquentNotificationRepository;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakPeriodUseCaseTest extends TestCase
{
    use RefreshDatabase;

    private StartBreakUseCase $startUseCase;

    private EndBreakUseCase $endUseCase;

    private RunBreakEndNotificationUseCase $notifyUseCase;

    private BreakPeriodRepository $breaks;

    private NotificationRepository $notifications;

    protected function setUp(): void
    {
        parent::setUp();

        $this->breaks = new EloquentBreakPeriodRepository;
        $this->notifications = new EloquentNotificationRepository;

        $logs = $this->app->make(RecordActivityUseCase::class);

        $this->startUseCase = new StartBreakUseCase($this->breaks, $logs);
        $this->endUseCase = new EndBreakUseCase($this->breaks, $logs);
        $this->notifyUseCase = new RunBreakEndNotificationUseCase($this->breaks, $this->notifications);
    }

    public function test_start_confirms_break_and_marks_week_exceptional(): void
    {
        $user = User::factory()->create();

        $result = $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-17'),
            CarbonImmutable::parse('2026-08-21'),
        );

        $this->assertTrue($result->breakPeriodId > 0);
        $this->assertSame('2026-08-17', $result->startDate);
        $this->assertSame('2026-08-21', $result->endDate);

        $this->assertDatabaseHas('break_periods', [
            'user_id' => $user->id,
            'start_date' => '2026-08-17',
            'end_date' => '2026-08-21',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BREAK_START,
        ]);

        $this->assertTrue($this->breaks->coversDate($user->id, CarbonImmutable::parse('2026-08-19')));
        $this->assertTrue($this->breaks->coversWeek($user->id, CarbonImmutable::parse('2026-08-19')));
        $this->assertFalse($this->breaks->coversDate($user->id, CarbonImmutable::parse('2026-08-22')));
    }

    public function test_start_rejects_end_before_start(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);

        $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-21'),
            CarbonImmutable::parse('2026-08-17'),
        );
    }

    public function test_start_rejects_second_active_break(): void
    {
        $user = User::factory()->create();

        $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-17'),
            CarbonImmutable::parse('2026-08-21'),
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-24'),
            CarbonImmutable::parse('2026-08-28'),
        );
    }

    public function test_end_ends_active_break_and_logs_summary(): void
    {
        $user = User::factory()->create();

        $start = $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-17'),
            CarbonImmutable::parse('2026-08-21'),
        );

        $result = $this->endUseCase->__invoke($user->id);

        $this->assertTrue($result->applied);
        $this->assertSame($start->breakPeriodId, $result->breakPeriodId);
        $this->assertSame(5, $result->durationDays);

        $this->assertDatabaseHas('break_periods', [
            'id' => $start->breakPeriodId,
            'status' => 'ended',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'event_type' => ActivityEventType::BREAK_END,
        ]);

        $this->assertFalse($this->breaks->coversWeek($user->id, CarbonImmutable::parse('2026-08-19')));
    }

    public function test_end_without_active_break_is_noop(): void
    {
        $user = User::factory()->create();

        $result = $this->endUseCase->__invoke($user->id);

        $this->assertFalse($result->applied);
        $this->assertNull($result->breakPeriodId);
    }

    public function test_h3_scan_creates_exactly_one_notification_per_break(): void
    {
        $user = User::factory()->create();

        $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-17'),
            CarbonImmutable::parse('2026-08-21'),
        );

        $today = CarbonImmutable::parse('2026-08-18');
        $created = $this->notifyUseCase->__invoke($user->id, $today);

        $this->assertCount(1, $created);
        $this->assertTrue($created[0]->type->equals(NotificationType::breakEnd()));
        $this->assertSame('2026-08-21', $created[0]->payload['end_date']);

        // Retry is idempotent: exactly one notification per break period.
        $again = $this->notifyUseCase->__invoke($user->id, $today);
        $this->assertCount(0, $again);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => NotificationType::BREAK_END,
        ]);
    }

    public function test_h3_scan_skips_breaks_not_ending_in_three_days(): void
    {
        $user = User::factory()->create();

        $this->startUseCase->__invoke(
            $user->id,
            CarbonImmutable::parse('2026-08-17'),
            CarbonImmutable::parse('2026-08-25'),
        );

        $created = $this->notifyUseCase->__invoke($user->id, CarbonImmutable::parse('2026-08-18'));

        $this->assertCount(0, $created);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_break_period_domain_validates_range(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new BreakPeriod(
            null,
            1,
            CarbonImmutable::parse('2026-08-21'),
            CarbonImmutable::parse('2026-08-17'),
            BreakPeriodStatus::active(),
        );
    }
}
