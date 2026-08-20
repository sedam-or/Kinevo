<?php

namespace Tests\Unit;

use App\Domain\Focus\FocusSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FocusSessionTest extends TestCase
{
    #[Test]
    public function session_computes_duration_from_interval(): void
    {
        $session = FocusSession::create(
            1,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            CarbonImmutable::parse('2026-08-18 09:45:00'),
            42,
        );

        $this->assertNull($session->id);
        $this->assertSame(42, $session->taskId);
        $this->assertSame(45, $session->durationMinutes);
    }

    #[Test]
    public function session_must_end_after_start(): void
    {
        $this->expectException(InvalidArgumentException::class);
        FocusSession::create(
            1,
            CarbonImmutable::parse('2026-08-18 09:45:00'),
            CarbonImmutable::parse('2026-08-18 09:00:00'),
        );
    }

    #[Test]
    public function session_serializes_with_id(): void
    {
        $session = FocusSession::create(
            1,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            CarbonImmutable::parse('2026-08-18 09:25:00'),
        )->withId(7);

        $array = $session->toArray();

        $this->assertSame(7, $array['id']);
        $this->assertNull($array['task_id']);
        $this->assertSame(25, $array['duration_minutes']);
        $this->assertSame('2026-08-18T09:00:00.000000Z', $array['started_at']);
    }

    #[Test]
    public function tracked_duration_is_rounded_to_minutes(): void
    {
        $session = FocusSession::fromTracked(
            1,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            CarbonImmutable::parse('2026-08-18 09:50:00'),
            2700,
            42,
        );

        $this->assertSame(42, $session->taskId);
        $this->assertSame(45, $session->durationMinutes);
    }

    #[Test]
    public function tracked_duration_is_at_least_one_minute(): void
    {
        $session = FocusSession::fromTracked(
            1,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            CarbonImmutable::parse('2026-08-18 09:00:30'),
            30,
        );

        $this->assertSame(1, $session->durationMinutes);
    }
}
