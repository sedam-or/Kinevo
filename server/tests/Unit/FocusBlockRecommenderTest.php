<?php

namespace Tests\Unit;

use App\Domain\Focus\FocusBlockRecommendation;
use App\Domain\Focus\FocusBlockRecommender;
use App\Domain\Focus\FocusSession;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FocusBlockRecommenderTest extends TestCase
{
    private FocusBlockRecommender $recommender;

    protected function setUp(): void
    {
        $this->recommender = new FocusBlockRecommender;
    }

    private function session(int $duration): FocusSession
    {
        return FocusSession::create(
            1,
            CarbonImmutable::parse('2026-08-18 09:00:00'),
            CarbonImmutable::parse('2026-08-18 09:00:00')->addMinutes($duration),
        );
    }

    #[Test]
    public function sparse_history_returns_baseline(): void
    {
        $recommendation = $this->recommender->recommend([], []);

        $this->assertSame(45, $recommendation->recommendedMinutes);
        $this->assertSame(FocusBlockRecommendation::BASIS_BASELINE, $recommendation->basis);
        $this->assertSame(0, $recommendation->sampleCount);
    }

    #[Test]
    public function task_patterns_take_precedence(): void
    {
        $taskSessions = [$this->session(40), $this->session(45), $this->session(50)];
        $userSessions = [$this->session(20), $this->session(25), $this->session(30)];

        $recommendation = $this->recommender->recommend($taskSessions, $userSessions);

        // avg 45 → rounds to 45
        $this->assertSame(45, $recommendation->recommendedMinutes);
        $this->assertSame(FocusBlockRecommendation::BASIS_TASK_PATTERNS, $recommendation->basis);
        $this->assertSame(3, $recommendation->sampleCount);
    }

    #[Test]
    public function user_patterns_fill_in_when_task_history_is_sparse(): void
    {
        $taskSessions = [$this->session(60)];
        $userSessions = [$this->session(25), $this->session(30), $this->session(35)];

        $recommendation = $this->recommender->recommend($taskSessions, $userSessions);

        // avg 30 → rounds to 30
        $this->assertSame(30, $recommendation->recommendedMinutes);
        $this->assertSame(FocusBlockRecommendation::BASIS_USER_PATTERNS, $recommendation->basis);
    }

    #[Test]
    public function anomalous_durations_are_excluded(): void
    {
        // 240 min and 2 min fall outside the [15,120] config bounds.
        $userSessions = [$this->session(30), $this->session(40), $this->session(240), $this->session(2)];

        $recommendation = $this->recommender->recommend([], $userSessions);

        // avg of 30,40 = 35 → rounds to 35
        $this->assertSame(35, $recommendation->recommendedMinutes);
        $this->assertSame(FocusBlockRecommendation::BASIS_USER_PATTERNS, $recommendation->basis);
    }

    #[Test]
    public function recommendation_is_clamped_and_rounded(): void
    {
        // avg 123 → clamp to 120 (max)
        $sessions = [$this->session(120), $this->session(125), $this->session(124)];

        $recommendation = $this->recommender->recommend($sessions, []);

        $this->assertSame(120, $recommendation->recommendedMinutes);
    }

    #[Test]
    public function configuration_is_injected_not_biological(): void
    {
        $recommender = new FocusBlockRecommender(
            minMinutes: 10,
            maxMinutes: 60,
            baselineMinutes: 30,
            roundTo: 10,
            minSamples: 2,
        );

        $recommendation = $recommender->recommend([], []);

        $this->assertSame(30, $recommendation->recommendedMinutes);

        $recommendation = $recommender->recommend([$this->session(22), $this->session(28)], []);
        $this->assertSame(30, $recommendation->recommendedMinutes); // avg 25 → round(2.5) = 3 → 30
    }

    #[Test]
    public function invalid_configuration_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FocusBlockRecommender(minMinutes: 90, maxMinutes: 30);
    }
}
