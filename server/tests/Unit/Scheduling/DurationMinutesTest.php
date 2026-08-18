<?php

namespace Tests\Unit\Scheduling;

use App\Domain\Scheduling\ValueObjects\DurationMinutes;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DurationMinutesTest extends TestCase
{
    #[Test]
    public function minutes_must_be_positive(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DurationMinutes(0);
    }

    #[Test]
    public function add_and_equals(): void
    {
        $this->assertSame(60, (new DurationMinutes(25))->add(new DurationMinutes(35))->value());
        $this->assertTrue((new DurationMinutes(25))->equals(new DurationMinutes(25)));
        $this->assertFalse((new DurationMinutes(25))->equals(new DurationMinutes(30)));
    }
}
