<?php

namespace Tests\Unit\Ai;

use App\Application\Ai\AICostSimulator;
use Tests\TestCase;

/**
 * COMMERCIAL PRICING DELTA D-004 — deterministic AI cost simulator.
 * Locks: determinism (same inputs => identical output), log-normal quantile
 * math, price_per_tokens scaling, unpriced guard, margin/safety flags.
 */
final class AICostSimulatorTest extends TestCase
{
    private const PRICE = [
        'currency' => 'USD',
        'input_price_minor' => 15,
        'output_price_minor' => 60,
        'cached_input_price_minor' => 15,
        'price_per_tokens' => 1_000_000,
        'effective_from' => '2026-01-01',
    ];

    public function test_simulates_deterministically_for_the_same_inputs(): void
    {
        config([
            'ai.cost.catalog' => ['openai.gpt-4o-mini' => self::PRICE],
            'ai.simulation.requests_per_month' => [
                'free' => ['mean' => 10, 'cv' => 0.5],
                'pro' => ['mean' => 50, 'cv' => 0.5],
                'power' => ['mean' => 100, 'cv' => 0.5],
            ],
            'billing.prices.pro.amount_major' => 49_900,
            'billing.prices.power.amount_major' => 89_900,
        ]);
        $simulator = app(AICostSimulator::class);

        $a = $simulator->simulate(['provider' => 'openai', 'model' => 'gpt-4o-mini']);
        $b = $simulator->simulate(['provider' => 'openai', 'model' => 'gpt-4o-mini']);

        $this->assertSame('ok', $a['status']);
        $this->assertSame($a, $b);
        foreach (['free', 'pro', 'power'] as $plan) {
            $this->assertArrayHasKey($plan, $a['plans']);
        }
    }

    public function test_margin_is_safe_for_cheap_usage_and_safe_flag_reflects_target(): void
    {
        config([
            'ai.cost.catalog' => ['openai.gpt-4o-mini' => self::PRICE],
            'ai.simulation.requests_per_month' => [
                'free' => ['mean' => 5, 'cv' => 0.5],
                'pro' => ['mean' => 20, 'cv' => 0.5],
                'power' => ['mean' => 40, 'cv' => 0.5],
            ],
            'ai.simulation.features' => [
                'note_summary' => ['input_tokens_mean' => 4000, 'cached_input_share' => 0.2, 'output_tokens_mean' => 800, 'cv' => 0.5],
            ],
            'ai.simulation.cache_hit_ratio' => 0.5,
            'ai.simulation.target_margin_low' => 0.10,
        ]);
        $simulator = app(AICostSimulator::class);

        $report = $simulator->simulate(['provider' => 'openai', 'model' => 'gpt-4o-mini']);

        // At Rp 49.900/month revenue (amount_major) and a tiny request volume,
        // margin must be positive and above the (lowered) target.
        $pro = $report['plans']['pro']['P50'];
        $this->assertGreaterThan(0, $pro['monthly_cogs_minor']);
        $this->assertLessThan($pro['revenue_major'], $pro['monthly_cogs_minor']);
        $this->assertTrue($pro['safe']);
    }

    public function test_per_request_cost_grows_towards_p99(): void
    {
        config(['ai.cost.catalog' => ['openai.gpt-4o-mini' => self::PRICE]]);
        $simulator = app(AICostSimulator::class);

        $report = $simulator->simulate(['provider' => 'openai', 'model' => 'gpt-4o-mini']);

        $fc = $report['feature_costs']['note_summary']['cost_per_request'];
        $this->assertLessThanOrEqual($fc['P95'], $fc['P50']);
        $this->assertLessThan($fc['P99'], $fc['P95']);
    }

    public function test_returns_unpriced_guard_when_catalog_absent(): void
    {
        config(['ai.cost.catalog' => []]);
        $simulator = app(AICostSimulator::class);

        $report = $simulator->simulate(['provider' => 'openai', 'model' => 'nope']);

        $this->assertSame('unpriced', $report['status']);
    }

    public function test_abuse_scenario_exceeds_p99_with_multiplier(): void
    {
        config([
            'ai.cost.catalog' => ['openai.gpt-4o-mini' => self::PRICE],
            'ai.simulation.abuse_multiplier' => 10,
        ]);
        $simulator = app(AICostSimulator::class);

        $report = $simulator->simulate(['provider' => 'openai', 'model' => 'gpt-4o-mini']);

        foreach (['free', 'pro', 'power'] as $plan) {
            $this->assertGreaterThan(
                $report['plans'][$plan]['P99']['requests'],
                $report['plans'][$plan]['ABUSE']['requests'],
            );
            $this->assertGreaterThan(
                $report['plans'][$plan]['P99']['monthly_cogs_minor'],
                $report['plans'][$plan]['ABUSE']['monthly_cogs_minor'],
            );
        }
    }
}
