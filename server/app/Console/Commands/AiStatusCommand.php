<?php

namespace App\Console\Commands;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiProviderException;
use Illuminate\Console\Command;

/**
 * AI provider status (SRS §17.8 telemetry). Safe metadata only; never private
 * content. Exits 1 when the provider is unavailable so scripts/Makefile can
 * gate on availability.
 */
final class AiStatusCommand extends Command
{
    protected $signature = 'ai:status';

    protected $description = 'Report the configured AI provider status';

    public function __construct(
        private readonly AiOrchestrator $ai,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $status = $this->ai->status();
        } catch (AiProviderException $e) {
            $this->error("AI provider unavailable: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['provider', 'model', 'available', 'latency_ms', 'error'],
            [[
                $status->provider,
                $status->model,
                $status->available ? 'yes' : 'no',
                (string) ($status->latencyMs ?? ''),
                $status->error ?? '',
            ]],
        );

        if (! $status->available) {
            $this->error('AI provider is unavailable.');

            return self::FAILURE;
        }

        $this->info('AI provider is available.');

        return self::SUCCESS;
    }
}
