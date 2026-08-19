<?php

namespace App\Domain\Focus;

/**
 * Recommended focus block duration (SRS §12.4). Durations are configuration,
 * never biological claims (design.md: show as a recommendation, avoid
 * "scientifically optimal").
 */
final readonly class FocusBlockRecommendation
{
    public const BASIS_TASK_PATTERNS = 'task_patterns';

    public const BASIS_USER_PATTERNS = 'user_patterns';

    public const BASIS_BASELINE = 'baseline';

    public function __construct(
        public int $recommendedMinutes,
        public string $basis,
        public int $sampleCount,
        public string $reason,
    ) {}
}
