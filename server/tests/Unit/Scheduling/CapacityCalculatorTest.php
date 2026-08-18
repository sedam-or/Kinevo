<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\CapacityCalculator;
use App\Domain\Scheduling\ValueObjects\DurationMinutes;
use App\Domain\Scheduling\WeekCapacitySample;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CapacityCalculatorTest extends TestCase
{
    private CapacityCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CapacityCalculator;
    }

    private function sample(int $planned, int $completed, string $tag = 'normal'): WeekCapacitySample
    {
        return new WeekCapacitySample(
            new DurationMinutes($planned),
            new DurationMinutes($completed),
            $tag,
        );
    }

    #[Test]
    public function ac09_sixty_percent_realization_reduces_load_to_about_sixty_percent(): void
    {
        $samples = [
            $this->sample(600, 360),
            $this->sample(600, 360),
            $this->sample(600, 360),
        ];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('REDUCE_LOAD', $capacity->recommendation);
        $this->assertSame(1800, $capacity->capacityMinutes->value());
        $this->assertSame(60, (int) round($capacity->capacityMinutes->value() / 3000 * 100));
    }

    #[Test]
    public function below_80_percent_reduces_load_proportionally(): void
    {
        $samples = [$this->sample(600, 450)];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('REDUCE_LOAD', $capacity->recommendation);
        $this->assertSame(2250, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function above_90_percent_offers_boost_without_burnout_signal(): void
    {
        $samples = [$this->sample(600, 570)];

        $capacity = $this->calculator->estimate($samples, 3000, burnoutSignal: false);

        $this->assertSame('BOOST_AVAILABLE', $capacity->recommendation);
        $this->assertSame(3000, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function burnout_signal_suppresses_boost(): void
    {
        $samples = [$this->sample(600, 570)];

        $capacity = $this->calculator->estimate($samples, 3000, burnoutSignal: true);

        $this->assertSame('MAINTAIN', $capacity->recommendation);
    }

    #[Test]
    public function emergency_and_break_weeks_are_excluded(): void
    {
        $samples = [
            $this->sample(600, 120, 'emergency'),
            $this->sample(600, 120, 'break'),
            $this->sample(600, 450),
            $this->sample(600, 450),
        ];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('REDUCE_LOAD', $capacity->recommendation);
        $this->assertSame(2250, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function all_weeks_excluded_uses_baseline_at_low_confidence(): void
    {
        $samples = [
            $this->sample(600, 100, 'emergency'),
            $this->sample(600, 100, 'break'),
        ];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('LOW', $capacity->confidence);
        $this->assertSame('MAINTAIN', $capacity->recommendation);
        $this->assertSame(3000, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function single_week_history_computes_at_low_confidence(): void
    {
        $samples = [$this->sample(600, 300)];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('LOW', $capacity->confidence);
        $this->assertSame('REDUCE_LOAD', $capacity->recommendation);
        $this->assertSame(1500, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function normal_band_maintains_capacity_at_medium_confidence(): void
    {
        $samples = [$this->sample(600, 500), $this->sample(600, 510)];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('MEDIUM', $capacity->confidence);
        $this->assertSame('MAINTAIN', $capacity->recommendation);
        $this->assertSame(3000, $capacity->capacityMinutes->value());
    }

    #[Test]
    public function four_weeks_give_high_confidence(): void
    {
        $samples = [
            $this->sample(600, 500),
            $this->sample(600, 500),
            $this->sample(600, 500),
            $this->sample(600, 500),
        ];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertSame('HIGH', $capacity->confidence);
    }

    #[Test]
    public function reason_is_always_present(): void
    {
        $samples = [$this->sample(600, 360), $this->sample(600, 360)];

        $capacity = $this->calculator->estimate($samples, 3000);

        $this->assertNotSame('', $capacity->reason);
    }
}
