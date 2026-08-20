<?php

namespace Tests\Unit;

use App\Domain\Execution\ExecutionSession;
use App\Domain\Execution\ValueObjects\ExecutionStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExecutionSessionTest extends TestCase
{
    #[Test]
    public function starting_creates_a_running_session(): void
    {
        $now = CarbonImmutable::parse('2026-08-19 09:00:00');

        $session = ExecutionSession::start(1, 42, $now);

        $this->assertNull($session->id);
        $this->assertSame(42, $session->taskId);
        $this->assertTrue($session->status->equals(ExecutionStatus::running()));
        $this->assertSame($now, $session->startedAt);
        $this->assertSame(0, $session->accumulatedSeconds);
        $this->assertNull($session->endedAt);
    }

    #[Test]
    public function elapsed_seconds_accumulate_while_running(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');

        $session = ExecutionSession::start(1, 42, $start);

        $this->assertSame(150, $session->elapsedSeconds(CarbonImmutable::parse('2026-08-19 09:02:30')));
    }

    #[Test]
    public function pause_banks_elapsed_and_stops_the_clock(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');

        $paused = ExecutionSession::start(1, 42, $start)
            ->pause(CarbonImmutable::parse('2026-08-19 09:25:00'));

        $this->assertTrue($paused->status->equals(ExecutionStatus::paused()));
        $this->assertSame(1500, $paused->accumulatedSeconds);
        $this->assertSame(1500, $paused->elapsedSeconds(CarbonImmutable::parse('2026-08-19 10:00:00')));
    }

    #[Test]
    public function resume_restarts_the_clock(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');

        $resumed = ExecutionSession::start(1, 42, $start)
            ->pause(CarbonImmutable::parse('2026-08-19 09:25:00'))
            ->resume(CarbonImmutable::parse('2026-08-19 09:40:00'));

        $this->assertTrue($resumed->status->equals(ExecutionStatus::running()));
        $this->assertSame(2100, $resumed->elapsedSeconds(CarbonImmutable::parse('2026-08-19 09:50:00')));
    }

    #[Test]
    public function complete_banks_elapsed_and_records_end(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');
        $end = CarbonImmutable::parse('2026-08-19 09:45:00');

        $completed = ExecutionSession::start(1, 42, $start)->complete($end);

        $this->assertTrue($completed->status->equals(ExecutionStatus::completed()));
        $this->assertSame(2700, $completed->accumulatedSeconds);
        $this->assertSame(2700, $completed->elapsedSeconds($end));
        $this->assertSame($end, $completed->endedAt);
    }

    #[Test]
    public function abandon_keeps_elapsed_for_audit(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');

        $abandoned = ExecutionSession::start(1, 42, $start)
            ->abandon(CarbonImmutable::parse('2026-08-19 09:10:00'));

        $this->assertTrue($abandoned->status->equals(ExecutionStatus::abandoned()));
        $this->assertSame(600, $abandoned->accumulatedSeconds);
        $this->assertNotNull($abandoned->endedAt);
    }

    #[Test]
    public function invalid_transitions_are_rejected(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');
        $completed = ExecutionSession::start(1, 42, $start)->complete($start->addMinute());

        $this->expectException(InvalidArgumentException::class);
        $completed->pause($start->addMinutes(2));
    }

    #[Test]
    public function pausing_a_paused_session_is_rejected(): void
    {
        $start = CarbonImmutable::parse('2026-08-19 09:00:00');
        $paused = ExecutionSession::start(1, 42, $start)->pause($start->addMinute());

        $this->expectException(InvalidArgumentException::class);
        $paused->pause($start->addMinutes(2));
    }

    #[Test]
    public function serialization_includes_elapsed_seconds(): void
    {
        $now = CarbonImmutable::parse('2026-08-19 09:00:00');

        $session = ExecutionSession::start(1, 42, $now)
            ->pause(CarbonImmutable::parse('2026-08-19 09:05:00'))
            ->withId(7);

        $array = $session->toArray(CarbonImmutable::parse('2026-08-19 09:30:00'));

        $this->assertSame(7, $array['id']);
        $this->assertSame('paused', $array['status']);
        $this->assertSame(300, $array['accumulated_seconds']);
        $this->assertSame(300, $array['elapsed_seconds']);
    }
}
