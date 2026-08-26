<?php

namespace Tests\Unit\Ai;

use App\Application\Ai\AiCostEstimator;
use Tests\TestCase;

/**
 * TASK-P25-001 — cost/price catalog arithmetic: per-request estimate from
 * versioned input/output rates (minor units per 1K tokens), provider.*
 * wildcard fallback, effective-from/until windows, and honest "unpriced"
 * (null) output when no active price exists. BYOK runs are never costed by
 * this estimator (P25-008).
 */
final class AiCostEstimatorTest extends TestCase
{
    private function catalog(): array
    {
        return [
            'openai.gpt-4o-mini' => [
                'currency' => 'USD',
                'input_price_minor' => 5,
                'output_price_minor' => 15,
                'effective_from' => '2026-01-01',
                'effective_until' => null,
            ],
            'ollama.*' => [
                'currency' => 'USD',
                'input_price_minor' => 0,
                'output_price_minor' => 0,
            ],
            'openai.future-model' => [
                'currency' => 'USD',
                'input_price_minor' => 1,
                'output_price_minor' => 1,
                'effective_from' => '2099-01-01',
            ],
        ];
    }

    private function estimator(): AiCostEstimator
    {
        config(['ai.cost.catalog' => $this->catalog()]);

        return new AiCostEstimator;
    }

    public function test_estimates_from_input_and_output_rates(): void
    {
        // 1000 in ×5 + 2000 out ×15 (per 1K) = 5 + 30 = 35 minor units.
        $est = $this->estimator()->estimate('openai', 'gpt-4o-mini', 1000, 2000);

        $this->assertSame(35, $est['estimated_cost_minor']);
        $this->assertSame('USD', $est['cost_currency']);
        $this->assertSame('catalog', $est['pricing_source']);
        $this->assertNotNull($est['pricing_snapshot_id']);
    }

    public function test_zero_token_request_estimates_zero(): void
    {
        $est = $this->estimator()->estimate('openai', 'gpt-4o-mini', 0, 0);

        $this->assertSame(0, $est['estimated_cost_minor']);
        $this->assertSame('catalog', $est['pricing_source']);
    }

    public function test_provider_wildcard_matches_any_model(): void
    {
        $est = $this->estimator()->estimate('ollama', 'llama3.1', 5000, 3000);

        $this->assertSame(0, $est['estimated_cost_minor']);
        $this->assertSame('catalog', $est['pricing_source']);
    }

    public function test_unknown_provider_is_honestly_unpriced(): void
    {
        $est = $this->estimator()->estimate('somevendor', 'future-model-x', 1000, 1000);

        $this->assertNull($est['estimated_cost_minor']);
        $this->assertNull($est['cost_currency']);
        $this->assertSame('unpriced', $est['pricing_source']);
    }

    public function test_inactive_price_window_is_not_applied(): void
    {
        // effective_from is in the future → not active today.
        $est = $this->estimator()->estimate('openai', 'future-model', 1000, 1000);

        $this->assertNull($est['estimated_cost_minor']);
        $this->assertSame('unpriced', $est['pricing_source']);
    }
}
