<?php

namespace Tests\Unit\Boosts;

use App\Domain\Boosts\BoostTarget;
use App\Domain\Boosts\ValueObjects\BoostTargetStatus;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BoostTargetTest extends TestCase
{
    #[Test]
    public function create_builds_an_active_target_scoped_to_the_validity_period(): void
    {
        $target = BoostTarget::create(
            1,
            7,
            '2026-08-17',
            '2026-08-21',
            60,
        );

        $this->assertNull($target->id);
        $this->assertSame(1, $target->userId);
        $this->assertSame(7, $target->breakPeriodId);
        $this->assertTrue($target->isActive());
        $this->assertSame(60, $target->targetPercent);
        $this->assertTrue($target->covers(CarbonImmutable::parse('2026-08-19')));
        $this->assertFalse($target->covers(CarbonImmutable::parse('2026-08-22')));
    }

    #[Test]
    public function rejects_invalid_percent_and_reversed_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BoostTarget::create(1, null, '2026-08-17', '2026-08-21', 0);

        $this->expectException(InvalidArgumentException::class);
        BoostTarget::create(1, null, '2026-08-21', '2026-08-17', 60);
    }

    #[Test]
    public function safety_cap_limits_proposed_percent(): void
    {
        $this->assertFalse(BoostTarget::exceedsSafetyCap(70));
        $this->assertTrue(BoostTarget::exceedsSafetyCap(71));
        $this->assertTrue(BoostTarget::exceedsSafetyCap(90));
        $this->assertSame(70, BoostTarget::SAFETY_CAP_PERCENT);
    }

    #[Test]
    public function end_marks_the_target_ended(): void
    {
        $target = BoostTarget::create(1, null, '2026-08-17', '2026-08-21', 60)->withId(3);

        $ended = $target->end();

        $this->assertTrue($ended->status->equals(BoostTargetStatus::ended()));
        $this->assertFalse($ended->isActive());
        $this->assertSame(3, $ended->id);
    }

    #[Test]
    public function to_array_exposes_the_contract(): void
    {
        $target = BoostTarget::create(1, 7, '2026-08-17', '2026-08-21', 70)->withId(3);

        $array = $target->toArray();

        $this->assertSame(3, $array['id']);
        $this->assertSame(7, $array['break_period_id']);
        $this->assertSame(70, $array['target_percent']);
        $this->assertSame('active', $array['status']);
        $this->assertSame('2026-08-17', $array['start_date']);
        $this->assertSame('2026-08-21', $array['end_date']);
    }
}
