<?php

namespace Tests\Unit;

use App\Domain\Adaptive\ContextFitScorer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContextFitScorerTest extends TestCase
{
    private ContextFitScorer $scorer;

    protected function setUp(): void
    {
        $this->scorer = new ContextFitScorer;
    }

    #[Test]
    public function sparse_data_returns_neutral_baseline(): void
    {
        $this->assertSame(ContextFitScorer::BASELINE, $this->scorer->score());
        $this->assertSame(ContextFitScorer::BASELINE, $this->scorer->score(energy: 0.2));
    }

    #[Test]
    public function high_energy_with_low_difficulty_is_a_good_fit(): void
    {
        $score = $this->scorer->score(energy: 0.9, difficulty: 0.2);

        $this->assertGreaterThan(0.7, $score);
    }

    #[Test]
    public function low_energy_with_high_difficulty_is_a_poor_fit(): void
    {
        $score = $this->scorer->score(energy: 0.2, difficulty: 0.9);

        $this->assertLessThan(0.4, $score);
        $this->assertLessThan($this->scorer->score(energy: 0.9, difficulty: 0.2), $score);
    }

    #[Test]
    public function high_stress_lowers_the_fit(): void
    {
        $calm = $this->scorer->score(energy: 0.7, stress: 0.2);
        $stressed = $this->scorer->score(energy: 0.7, stress: 0.9);

        $this->assertLessThan($calm, $stressed);
    }

    #[Test]
    public function familiarity_improves_the_fit(): void
    {
        $unknown = $this->scorer->score(difficulty: 0.5, familiarity: null);
        $familiar = $this->scorer->score(difficulty: 0.5, familiarity: 0.9);

        $this->assertGreaterThan($unknown, $familiar);
    }

    #[Test]
    public function score_is_deterministic(): void
    {
        $a = $this->scorer->score(energy: 0.6, stress: 0.5, difficulty: 0.7, familiarity: 0.8);

        $this->assertSame($a, $this->scorer->score(energy: 0.6, stress: 0.5, difficulty: 0.7, familiarity: 0.8));
    }
}
