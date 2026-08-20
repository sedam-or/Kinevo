<?php

namespace Tests\Unit\Analytics;

use App\Domain\Analytics\WorkLifeRatio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WorkLifeRatioTest extends TestCase
{
    #[Test]
    public function normative_formula_splits_productive_and_recharge(): void
    {
        $ratio = WorkLifeRatio::fromMinutes(50, 15);

        $this->assertSame(50, $ratio->productiveMinutes);
        $this->assertSame(15, $ratio->rechargeMinutes);
        $this->assertSame(65, $ratio->totalMinutes());
        $this->assertSame(round(50 / 65, 4), $ratio->workRatio);
        $this->assertSame(round(15 / 65, 4), $ratio->rechargeRatio);
    }

    #[Test]
    public function zero_totals_yield_zero_ratios(): void
    {
        $ratio = WorkLifeRatio::fromMinutes(0, 0);

        $this->assertSame(0.0, $ratio->workRatio);
        $this->assertSame(0.0, $ratio->rechargeRatio);
        $this->assertSame('no_data', $ratio->band());
    }

    #[Test]
    public function recharge_counts_as_recharge_not_productive_time(): void
    {
        // FR-05 Business Rule: Recharge is Recharge, never Productive Time.
        $ratio = WorkLifeRatio::fromMinutes(60, 60);

        $this->assertSame(0.5, $ratio->workRatio);
        $this->assertSame(0.5, $ratio->rechargeRatio);
    }

    #[Test]
    public function band_describes_distribution_not_health(): void
    {
        $this->assertSame('work_leaning', WorkLifeRatio::fromMinutes(500, 50)->band());
        $this->assertSame('recharge_leaning', WorkLifeRatio::fromMinutes(100, 200)->band());
        $this->assertSame('balanced', WorkLifeRatio::fromMinutes(120, 60)->band());
    }

    #[Test]
    public function carries_time_balance_disclaimer(): void
    {
        $ratio = WorkLifeRatio::fromMinutes(50, 15);

        $this->assertSame('Time-balance indicator. Not a health diagnosis.', $ratio->toArray()['disclaimer']);
        $this->assertSame('balanced', $ratio->band());
    }
}
