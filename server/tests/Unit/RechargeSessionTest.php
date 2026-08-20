<?php

namespace Tests\Unit;

use App\Domain\Recharge\RechargeSession;
use App\Domain\Recharge\ValueObjects\RechargeStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RechargeSessionTest extends TestCase
{
    #[Test]
    public function starting_creates_a_running_session(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 10:00:00');

        $session = RechargeSession::start(1, $now);

        $this->assertNull($session->id);
        $this->assertTrue($session->status->equals(RechargeStatus::running()));
        $this->assertSame($now, $session->startedAt);
        $this->assertSame(0, $session->accumulatedSeconds);
        $this->assertNull($session->durationMinutes);
        $this->assertNull($session->endedAt);
    }

    #[Test]
    public function elapsed_seconds_accumulate_while_running(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');

        $session = RechargeSession::start(1, $start);

        $this->assertSame(150, $session->elapsedSeconds(CarbonImmutable::parse('2026-08-20 10:02:30')));
    }

    #[Test]
    public function pause_banks_elapsed_and_stops_the_clock(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');

        $paused = RechargeSession::start(1, $start)
            ->pause(CarbonImmutable::parse('2026-08-20 10:10:00'));

        $this->assertTrue($paused->status->equals(RechargeStatus::paused()));
        $this->assertSame(600, $paused->accumulatedSeconds);
        $this->assertSame(600, $paused->elapsedSeconds(CarbonImmutable::parse('2026-08-20 10:30:00')));
    }

    #[Test]
    public function resume_restarts_the_clock(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');

        $resumed = RechargeSession::start(1, $start)
            ->pause(CarbonImmutable::parse('2026-08-20 10:10:00'))
            ->resume(CarbonImmutable::parse('2026-08-20 10:20:00'));

        $this->assertTrue($resumed->status->equals(RechargeStatus::running()));
        $this->assertSame(1200, $resumed->elapsedSeconds(CarbonImmutable::parse('2026-08-20 10:30:00')));
    }

    #[Test]
    public function complete_records_tracked_duration_in_minutes(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');
        $end = CarbonImmutable::parse('2026-08-20 10:15:00');

        $completed = RechargeSession::start(1, $start)->complete($end);

        $this->assertTrue($completed->status->equals(RechargeStatus::completed()));
        $this->assertSame(900, $completed->accumulatedSeconds);
        $this->assertSame(15, $completed->durationMinutes);
        $this->assertSame($end, $completed->endedAt);
    }

    #[Test]
    public function complete_rounds_short_sessions_to_one_minute(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');

        $completed = RechargeSession::start(1, $start)->complete($start->addSeconds(30));

        $this->assertSame(1, $completed->durationMinutes);
    }

    #[Test]
    public function abandon_keeps_elapsed_but_no_duration(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');

        $abandoned = RechargeSession::start(1, $start)
            ->abandon(CarbonImmutable::parse('2026-08-20 10:05:00'));

        $this->assertTrue($abandoned->status->equals(RechargeStatus::abandoned()));
        $this->assertSame(300, $abandoned->accumulatedSeconds);
        $this->assertNull($abandoned->durationMinutes);
        $this->assertNotNull($abandoned->endedAt);
    }

    #[Test]
    public function invalid_transitions_are_rejected(): void
    {
        $start = CarbonImmutable::parse('2026-08-20 10:00:00');
        $completed = RechargeSession::start(1, $start)->complete($start->addMinute());

        $this->expectException(InvalidArgumentException::class);
        $completed->pause($start->addMinutes(2));
    }

    #[Test]
    public function serialization_includes_tracked_elapsed_and_duration(): void
    {
        $now = CarbonImmutable::parse('2026-08-20 10:00:00');

        $session = RechargeSession::start(1, $now)
            ->complete(CarbonImmutable::parse('2026-08-20 10:15:00'))
            ->withId(7);

        $array = $session->toArray($now);

        $this->assertSame(7, $array['id']);
        $this->assertSame('completed', $array['status']);
        $this->assertSame(900, $array['accumulated_seconds']);
        $this->assertSame(15, $array['duration_minutes']);
        $this->assertNotNull($array['ended_at']);
    }
}
