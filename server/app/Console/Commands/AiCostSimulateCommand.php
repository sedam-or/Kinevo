<?php

namespace App\Console\Commands;

use App\Application\Ai\AICostSimulator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;

/**
 * COMMERCIAL PRICING DELTA D-004 / P32-007 — run the deterministic AI cost
 * simulator and dump the report for the quota decision.
 *
 * Usage examples:
 *   php artisan ai:cost-simulate --provider=openai --model=gpt-4o-mini
 *   php artisan ai:cost-simulate --provider=openai --model=gpt-4o-mini --json
 *   php artisan ai:cost-simulate --provider=deepseek --model=deepseek-chat
 *     --price-override='{"currency":"USD","input_price_minor":28,"output_price_minor":42,
 *     "cached_input_price_minor":3,"price_per_tokens":1000000}'
 */
final class AiCostSimulateCommand extends Command
{
    protected $signature = 'ai:cost-simulate
        {--provider= : Provider key (e.g. openai, deepseek, ollama)}
        {--model= : Model name}
        {--cache-hit-ratio= : Cache hit ratio 0..1 (default config)}
        {--price-override= : JSON price entry if the catalog has no active entry}
        {--json : Emit machine-readable JSON}
        {--save= : Write report to storage (default storage/app/ai-cost-simulation.json)}';

    protected $description = 'Run the deterministic AI cost simulator (P32-007 / D-004) for the quota decision';

    public function handle(AICostSimulator $simulator): int
    {
        $provider = (string) $this->option('provider');
        if ($provider === '') {
            $this->error('--provider is required.');

            return self::FAILURE;
        }
        $model = (string) $this->option('model');
        if ($model === '') {
            $model = (string) config("ai.{$provider}.model", '');
        }

        $override = null;
        $rawOverride = (string) $this->option('price-override');
        if ($rawOverride !== '') {
            try {
                $override = json_decode($rawOverride, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->error("price-override is not valid JSON: {$e->getMessage()}");

                return self::FAILURE;
            }
        }

        $options = ['provider' => $provider, 'model' => $model, 'price' => $override];
        $cacheHit = $this->option('cache-hit-ratio');
        if ($cacheHit !== null) {
            $options['cache_hit_ratio'] = (float) $cacheHit;
        }

        $report = $simulator->simulate($options);

        if (($report['status'] ?? '') === 'unpriced') {
            $this->warn((string) ($report['note'] ?? 'No active price for this model.'));

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Plan', 'Scenario', 'Requests', 'COGS (minor)', 'Revenue (Rp)', 'Margin', 'Safe'],
            $this->rows($report),
        );

        $savePath = $this->option('save') === null ? 'app/ai-cost-simulation.json' : (string) $this->option('save');
        Storage::put($savePath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("Report saved to storage: {$savePath}");

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $report */
    private function rows(array $report): array
    {
        $rows = [];
        foreach (($report['plans'] ?? []) as $plan => $scenarios) {
            foreach ($scenarios as $scenario => $row) {
                $rows[] = [
                    $plan,
                    $scenario,
                    (string) $row['requests'],
                    (string) $row['monthly_cogs_minor'],
                    (string) $row['revenue_major'],
                    $row['margin'] === null ? '-' : sprintf('%.1f%%', $row['margin'] * 100),
                    isset($row['safe']) && $row['safe'] ? 'yes' : ($row['safe'] === null ? 'n/a' : 'NO'),
                ];
            }
        }

        return $rows;
    }
}
