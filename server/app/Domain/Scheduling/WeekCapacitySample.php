<?php

namespace App\Domain\Scheduling;

use App\Domain\Scheduling\ValueObjects\DurationMinutes;
use InvalidArgumentException;

/**
 * One week of historical scheduling data used by the capacity feedback loop
 * (FR-49). Emergency/Break weeks are tagged so they can be excluded or
 * weighted separately (FR-49 Exception Flows).
 */
final class WeekCapacitySample
{
    private const TAGS = ['normal', 'emergency', 'break'];

    public function __construct(
        public readonly DurationMinutes $plannedMinutes,
        public readonly DurationMinutes $completedMinutes,
        public readonly string $tag = 'normal',
    ) {
        if (! in_array($tag, self::TAGS, true)) {
            throw new InvalidArgumentException("Unsupported week tag: {$tag}");
        }
    }

    public function realizationRatio(): float
    {
        if ($this->plannedMinutes->value() === 0) {
            return 0.0;
        }

        return min(1.0, $this->completedMinutes->value() / $this->plannedMinutes->value());
    }

    public function isEligible(): bool
    {
        return $this->tag === 'normal';
    }
}
