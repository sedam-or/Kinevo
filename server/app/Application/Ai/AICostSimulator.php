<?php

namespace App\Application\Ai;

use Illuminate\Support\Carbon;

/**
 * COMMERCIAL PRICING DELTA D-004 / P32-007 — deterministic AI cost simulator.
 *
 * Models monthly hosted-AI cost per tier from per-feature usage profiles and a
 * versioned price catalog (config/ai.php `cost.catalog` + `simulation`), so the
 * included AI allowance can be derived from economics instead of invented.
 *
 * Determinism guarantee: every distribution is represented analytically via
 * log-normal quantiles Z(P50..P99) computed from (mean, cv) — no randomness,
 * no RNG, same inputs => identical output.
 *
 * Honesty boundaries (master RELU 3.5 / revisi-finance §7):
 *  - Profiles are DEPRECATED-BASELINE assumptions in config until real usage
 *    instrumentation supplies observed distributions (P32/P37).
 *  - Catalog prices MUST be verified against official provider sources before
 *    locking anything; `price_per_tokens` scales rates (e.g. 1_000_000 when
 *    rates come from provider "per 1M tokens" sheets).
 *  - Output is an economic planning aid for a DECISION_REQUIRED quota call,
 *    never an invoice and never a market price claim.
 */
final readonly class AICostSimulator
{
    /** Standard-normal quantiles for a log-normal distribution. */
    private const Z = ['P50' => 0.0, 'P75' => 0.6745, 'P90' => 1.2816, 'P95' => 1.6449, 'P99' => 2.3263];

    private const FEATURES = ['goal_breakdown', 'note_summary', 'task_extraction', 'planning', 'deep_analysis', 'wrapped_narrative'];

    /**
     * @param  array{provider: string, model: string, cache_hit_ratio?: float, price?: array<string, mixed>|null}  $options
     * @return array<string, mixed>
     */
    public function simulate(array $options): array
    {
        $provider = (string) $options['provider'];
        $model = (string) $options['model'];
        $cacheHit = (float) ($options['cache_hit_ratio'] ?? (float) config('ai.simulation.cache_hit_ratio', 0.5));

        $price = $this->resolvePrice($provider, $model, $options['price'] ?? null);
        if ($price === null) {
            return ['status' => 'unpriced', 'note' => 'No active catalog entry for this provider/model. Load a verified price into config/ai.php cost.catalog or pass --price-override. Nothing estimated.', 'options' => $options];
        }

        $profiles = $this->featureProfiles();
        $plans = $this->planProfiles();
        $target = [(float) config('ai.simulation.target_margin_low', 0.30), (float) config('ai.simulation.target_margin_high', 0.50)];
        $abuseMultiplier = (int) config('ai.simulation.abuse_multiplier', 15);

        $featureCosts = [];
        foreach (self::FEATURES as $feature) {
            $p = $profiles[$feature] ?? [];
            if ($p === []) {
                continue;
            }
            $featureCosts[$feature] = [
                'cost_per_request' => [
                    'P50' => $this->featureCostAtZ($price, $cacheHit, $p, self::Z['P50']),
                    'P95' => $this->featureCostAtZ($price, $cacheHit, $p, self::Z['P95']),
                    'P99' => $this->featureCostAtZ($price, $cacheHit, $p, self::Z['P99']),
                ],
            ];
        }

        $planRows = [];
        foreach ($plans as $planCode => $reqProfile) {
            $revenueMinor = (int) config("billing.prices.{$planCode}.amount_minor", 0);
            $planRows[$planCode] = [];
            foreach (array_merge(array_keys(self::Z), ['ABUSE']) as $scenario) {
                $requests = $scenario === 'ABUSE'
                    ? (int) round($this->quantileTokens((float) $reqProfile['mean'], (float) $reqProfile['cv'], self::Z['P99']) * $abuseMultiplier)
                    : (int) round($this->quantileTokens((float) $reqProfile['mean'], (float) $reqProfile['cv'], self::Z[$scenario]));
                $monthlyCost = 0;
                foreach ($featureCosts as $fc) {
                    // Feature mix weights are equal by default (no invented
                    // caller behaviour); override via config later with data.
                    $monthlyCost += (int) round($fc['cost_per_request']['P50'] * $requests / max(1, count($featureCosts)));
                }
                $margin = $revenueMinor > 0 ? ($revenueMinor - $monthlyCost) / $revenueMinor : null;
                $planRows[$planCode][$scenario] = [
                    'requests' => $requests,
                    'monthly_cogs_minor' => $monthlyCost,
                    'revenue_minor' => $revenueMinor,
                    'margin' => $margin === null ? null : round($margin, 3),
                    'safe' => $margin === null ? null : $margin >= $target[0],
                ];
            }
        }

        return [
            'status' => 'ok',
            'provider' => $provider,
            'model' => $model,
            'cache_hit_ratio' => $cacheHit,
            'currency' => $price['currency'],
            'price_per_tokens' => $price['price_per_tokens'],
            'catalog_entry' => $price['entry'],
            'pricing_snapshot_id' => $price['snapshot_id'],
            'profiles' => [
                'features' => $profiles,
                'plans_requests_per_month' => $plans,
                'note' => 'DEPRECATED-BASELINE assumptions until real usage data is instrumented (P32/P37).',
            ],
            'feature_costs' => $featureCosts,
            'plans' => $planRows,
            'target_margin_range' => $target,
            'decision_required' => 'Hosted AI quota per tier must be locked from this analysis — see report, then owner decision.',
        ];
    }

    /** @param array<string, mixed>|null $priceOverride */
    private function resolvePrice(string $provider, string $model, ?array $priceOverride): ?array
    {
        $catalog = (array) config('ai.cost.catalog', []);
        $now = Carbon::now();
        $entry = null;

        foreach ([$provider.'.'.$model, $provider.'.*'] as $key) {
            if (isset($catalog[$key]) && is_array($catalog[$key])) {
                $candidate = $catalog[$key];
                $from = isset($candidate['effective_from']) ? Carbon::parse($candidate['effective_from']) : null;
                $until = isset($candidate['effective_until']) ? Carbon::parse($candidate['effective_until']) : null;
                if (($from === null || $now->greaterThanOrEqualTo($from)) && ($until === null || $now->lessThanOrEqualTo($until))) {
                    $entry = $candidate;
                    break;
                }
            }
        }

        if ($entry === null && is_array($priceOverride)) {
            $entry = $priceOverride;
        }
        if ($entry === null) {
            return null;
        }

        $input = (int) ($entry['input_price_minor'] ?? 0);
        $output = (int) ($entry['output_price_minor'] ?? 0);
        $cached = (int) ($entry['cached_input_price_minor'] ?? $input);
        $per = (int) ($entry['price_per_tokens'] ?? 1000);

        return [
            'entry' => $entry,
            'currency' => (string) ($entry['currency'] ?? config('ai.cost.default_currency', 'USD')),
            'input_price_minor' => $input,
            'cached_input_price_minor' => $cached,
            'output_price_minor' => $output,
            'price_per_tokens' => $per,
            'snapshot_id' => substr(hash('sha256', $provider.'|'.$model.'|'.json_encode($entry)), 0, 16),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function featureProfiles(): array
    {
        return (array) config('ai.simulation.features', []);
    }

    /** @return array<string, array{mean: int, cv: float}> */
    private function planProfiles(): array
    {
        return [
            'free' => ['mean' => (int) config('ai.simulation.requests_per_month.free.mean', 15), 'cv' => (float) config('ai.simulation.requests_per_month.free.cv', 0.6)],
            'pro' => ['mean' => (int) config('ai.simulation.requests_per_month.pro.mean', 60), 'cv' => (float) config('ai.simulation.requests_per_month.pro.cv', 0.5)],
            'power' => ['mean' => (int) config('ai.simulation.requests_per_month.power.mean', 120), 'cv' => (float) config('ai.simulation.requests_per_month.power.cv', 0.5)],
        ];
    }

    /** @param array<string, mixed> $price */
    private function featureCostAtZ(array $price, float $cacheHit, array $profile, float $z): float
    {
        $in = $this->quantileTokens((float) $profile['input_tokens_mean'], (float) $profile['cv'], $z);
        $out = $this->quantileTokens((float) $profile['output_tokens_mean'], (float) $profile['cv'], $z);
        $cached = $this->quantileTokens((float) $profile['input_tokens_mean'] * (float) $profile['cached_input_share'], (float) $profile['cv'], $z);
        $per = (int) $price['price_per_tokens'];

        // cache-hit splits the cached share at the (discounted) cached rate.
        $liveInput = (int) round($in * (1 - $cacheHit) + $cached * $cacheHit);

        return ((int) $price['input_price_minor'] * $liveInput + (int) $price['output_price_minor'] * $out) / $per;
    }

    private function quantileTokens(float $mean, float $cv, float $z): float
    {
        if ($mean <= 0 || $cv < 0) {
            return 0.0;
        }
        if ($cv === 0.0 || $cv <= 0.001) {
            return $mean;
        }
        $sigmaSq = log(1 + $cv * $cv);
        $sigma = sqrt($sigmaSq);
        $mu = log($mean) - 0.5 * $sigmaSq;

        return exp($mu + $sigma * $z);
    }
}
