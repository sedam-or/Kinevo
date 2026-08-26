<?php

namespace App\Console\Commands;

use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * End-to-end smoke check for the configured AI provider (Ollama dev adapter).
 * Sends a tiny deterministic prompt; exits 0 on success, 1 when the provider
 * is unavailable. Non-mutating — no domain state is touched. Calls the
 * orchestrator directly and intentionally bypasses user credit metering
 * (it is a diagnostic command, not a user AI request — TASK-P25-003).
 */
final class AiSmokeCommand extends Command
{
    protected $signature = 'ai:smoke {prompt=Reply with: OK}';

    protected $description = 'Send a tiny prompt through the configured AI provider';

    public function __construct(
        private readonly AiOrchestrator $ai,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $response = $this->ai->generate(0, new AiRequest(
                new AiRole('task_extraction'),
                (string) $this->argument('prompt'),
            ));
        } catch (AiProviderException $e) {
            $this->error("AI provider unavailable: {$e->getMessage()}");

            return self::FAILURE;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line("Provider: {$response->provider} ({$response->model})");
        $this->line("Latency:  {$response->latencyMs} ms");
        $this->line("Response: {$response->text}");

        return self::SUCCESS;
    }
}
