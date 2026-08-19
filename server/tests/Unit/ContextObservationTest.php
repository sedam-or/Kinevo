<?php

namespace Tests\Unit;

use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContextObservationTest extends TestCase
{
    #[Test]
    public function signal_level_is_bounded_to_one_to_ten(): void
    {
        $this->assertSame(7, SignalLevel::fromInt(7)->value());
        $this->assertTrue(SignalLevel::fromInt(1)->equals(SignalLevel::fromInt(1)));
        $this->assertFalse(SignalLevel::fromInt(1)->equals(SignalLevel::fromInt(2)));

        $this->expectException(InvalidArgumentException::class);
        new SignalLevel(11);
    }

    #[Test]
    public function observation_requires_at_least_one_signal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContextObservation::create(1);
    }

    #[Test]
    public function observation_can_be_created_with_all_signals(): void
    {
        $observation = ContextObservation::create(
            1,
            42,
            SignalLevel::fromInt(8),
            SignalLevel::fromInt(3),
            SignalLevel::fromInt(6),
            SignalLevel::fromInt(7),
            2,
            1,
            25,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
        );

        $this->assertNull($observation->id);
        $this->assertSame(42, $observation->taskId);
        $this->assertSame(8, $observation->energy->value);
        $this->assertSame(3, $observation->stress->value);
        $this->assertSame(2, $observation->interruptionCount);
        $this->assertSame(25, $observation->focusDurationMinutes);
        $this->assertSame('2026-08-18 09:00:00', $observation->checkedAt->toDateTimeString());
    }

    #[Test]
    public function observation_rejects_negative_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ContextObservation::create(1, energy: SignalLevel::fromInt(5), interruptionCount: -1);
    }

    #[Test]
    public function observation_serializes_with_id(): void
    {
        $observation = ContextObservation::create(
            1,
            energy: SignalLevel::fromInt(6),
            checkedAt: CarbonImmutable::parse('2026-08-18 09:00:00'),
        )->withId(3);

        $array = $observation->toArray();

        $this->assertSame(3, $array['id']);
        $this->assertSame(6, $array['energy_level']);
        $this->assertNull($array['stress_level']);
        $this->assertSame('2026-08-18T09:00:00.000000Z', $array['checked_at']);
    }
}
