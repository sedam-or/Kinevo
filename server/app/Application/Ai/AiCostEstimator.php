<?php

namespace App\Application\Ai;

use Illuminate\Support\Carbon;

/**
 * TASK-P25-001 — estimates Kinevo-hosted inference cost from a versioned price
 * catalog (config/ai.php `cost.catalog`). The catalog is PRODUCT DATA; it ships
 * empty and the OWNER MUST populate real provider prices. Estimated cost is
 * explicitly NOT a financial truth (it differs from the provider invoice); it
 * feeds hard cost caps (P25-007) and reporting (P25-010). BYOK runs (P25-008)
 * are never costed here — the user bears that spend.
 *
 * Per request: cost = input_tokens x input_rate + output_tokens x output_rate
 * (rates are integer minor units per 1K tokens by default; `price_per_tokens`
 * e.g. 1_000_000 for provider-sheet rates scales the divisor accordingly).
 * Active entry is the one whose effective_from/until window contains now; a
 * `provider.*` key matches any model.
 */
final readonly class AiCostEstimator
{
    public function estimate(string $provider, string $model, ?int $inputTokens, ?int $outputTokens): array
    {
        $entry = $this->activeEntry($provider, $model);

        if ($entry === null) {
            return [
                'estimated_cost_minor' => null,
                'cost_currency' => null,
                'pricing_source' => 'unpriced',
                'pricing_snapshot_id' => null,
            ];
        }

        $in = (int) ($inputTokens ?? 0);
        $out = (int) ($outputTokens ?? 0);
        $per = (int) ($entry['price_per_tokens'] ?? 1000);
        $cost = ((int) ($entry['input_price_minor'] ?? 0) * $in
            + (int) ($entry['output_price_minor'] ?? 0) * $out) / $per;

        return [
            'estimated_cost_minor' => (int) round($cost),
            'cost_currency' => $entry['currency'] ?? config('ai.cost.default_currency', 'USD'),
            'pricing_source' => 'catalog',
            'pricing_snapshot_id' => $this->snapshotId($provider, $model, $entry),
        ];
    }

    /** @return array<string, mixed>|null */
    private function activeEntry(string $provider, string $model): ?array
    {
        $catalog = (array) config('ai.cost.catalog', []);
        $now = Carbon::now();

        foreach ([$provider.'.'.$model, $provider.'.*'] as $key) {
            if (! isset($catalog[$key]) || ! is_array($catalog[$key])) {
                continue;
            }
            $entry = $catalog[$key];

            $from = isset($entry['effective_from']) ? Carbon::parse($entry['effective_from']) : null;
            $until = isset($entry['effective_until']) ? Carbon::parse($entry['effective_until']) : null;

            if (($from === null || $now->greaterThanOrEqualTo($from))
                && ($until === null || $now->lessThanOrEqualTo($until))) {
                return $entry;
            }
        }

        return null;
    }

    private function snapshotId(string $provider, string $model, array $entry): string
    {
        return substr(hash('sha256', $provider.'|'.$model.'|'.json_encode($entry)), 0, 16);
    }
}
