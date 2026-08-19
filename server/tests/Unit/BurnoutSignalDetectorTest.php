<?php

namespace Tests\Unit;

use App\Domain\Adaptive\BurnoutSignalDetector;
use App\Domain\Adaptive\ContextObservation;
use App\Domain\Adaptive\ValueObjects\SignalLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BurnoutSignalDetectorTest extends TestCase
{
    private BurnoutSignalDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new BurnoutSignalDetector;
    }

    private function observation(?SignalLevel $energy, ?SignalLevel $stress): ContextObservation
    {
        return ContextObservation::create(1, energy: $energy, stress: $stress);
    }

    #[Test]
    public function insufficient_history_never_raises_signal(): void
    {
        $few = [
            $this->observation(SignalLevel::fromInt(2), SignalLevel::fromInt(9)),
            $this->observation(SignalLevel::fromInt(3), SignalLevel::fromInt(8)),
        ];

        $signal = $this->detector->detect($few);

        $this->assertFalse($signal->active);
        $this->assertSame(2, $signal->sampleCount);
    }

    #[Test]
    public function sustained_high_stress_with_low_energy_raises_signal(): void
    {
        $observations = [
            $this->observation(SignalLevel::fromInt(3), SignalLevel::fromInt(8)),
            $this->observation(SignalLevel::fromInt(2), SignalLevel::fromInt(9)),
            $this->observation(SignalLevel::fromInt(3), SignalLevel::fromInt(8)),
        ];

        $signal = $this->detector->detect($observations);

        $this->assertTrue($signal->active);
        $this->assertSame(3, $signal->sampleCount);
    }

    #[Test]
    public function high_stress_with_normal_energy_does_not_raise_signal(): void
    {
        $observations = [
            $this->observation(SignalLevel::fromInt(6), SignalLevel::fromInt(8)),
            $this->observation(SignalLevel::fromInt(7), SignalLevel::fromInt(9)),
            $this->observation(SignalLevel::fromInt(6), SignalLevel::fromInt(8)),
        ];

        $signal = $this->detector->detect($observations);

        $this->assertFalse($signal->active);
    }

    #[Test]
    public function low_energy_with_normal_stress_does_not_raise_signal(): void
    {
        $observations = [
            $this->observation(SignalLevel::fromInt(2), SignalLevel::fromInt(4)),
            $this->observation(SignalLevel::fromInt(3), SignalLevel::fromInt(5)),
            $this->observation(SignalLevel::fromInt(2), SignalLevel::fromInt(4)),
        ];

        $signal = $this->detector->detect($observations);

        $this->assertFalse($signal->active);
    }
}
