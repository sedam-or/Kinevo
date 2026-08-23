<?php
namespace Tests\Unit\Ai;
use App\Application\Ai\GetAiProviderStatusUseCase;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use PHPUnit\Framework\TestCase;

/**
 * TASK-P17-007 — the canonical AI status state machine. One mapping, one
 * source of truth; every surface derives its wording from these states.
 */
final class AiProviderStateMappingTest extends TestCase
{
    private function config(?string $provider = 'ollama', bool $enabled = true): AiProviderConfig
    {
        return new AiProviderConfig(
            provider: $provider,
            enabled: $enabled,
            model: 'llama3.1',
            baseUrl: 'http://localhost:11434',
            apiKey: null,
        );
    }

    private function probe(bool $available = true, ?int $latencyMs = 40, string $provider = 'ollama'): AiProviderStatus
    {
        return new AiProviderStatus($provider, 'llama3.1', $available, $latencyMs, null);
    }

    public function test_disabled_config_maps_to_disabled(): void
    {
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_DISABLED,
            GetAiProviderStatusUseCase::stateFor($this->config(enabled: false), $this->probe()),
        );
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_DISABLED,
            GetAiProviderStatusUseCase::stateFor($this->config(provider: 'disabled'), $this->probe()),
        );
    }

    public function test_no_config_with_working_deployment_maps_to_connected(): void
    {
        // Deployment default (e.g. AI_PROVIDER=ollama) without a saved record
        // is usable — it follows the availability path, not not_configured.
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_CONNECTED,
            GetAiProviderStatusUseCase::stateFor(null, $this->probe()),
        );
    }

    public function test_enabled_openai_without_api_key_is_not_configured(): void
    {
        // Missing required setup is distinct from reachable-but-down.
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_NOT_CONFIGURED,
            GetAiProviderStatusUseCase::stateFor($this->config(provider: 'openai'), $this->probe(available: false)),
        );
    }

    public function test_no_config_with_disabled_deployment_maps_to_disabled(): void
    {
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_DISABLED,
            GetAiProviderStatusUseCase::stateFor(null, $this->probe(provider: 'disabled')),
        );
    }

    public function test_enabled_but_unreachable_maps_to_unavailable(): void
    {
        $status = new AiProviderStatus('ollama', 'llama3.1', false, null, 'Ollama is unreachable.');
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_UNAVAILABLE,
            GetAiProviderStatusUseCase::stateFor($this->config(), $status),
        );
    }

    public function test_reachable_within_budget_maps_to_connected(): void
    {
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_CONNECTED,
            GetAiProviderStatusUseCase::stateFor($this->config(), $this->probe(latencyMs: 120)),
        );
    }

    public function test_reachable_but_slow_maps_to_degraded(): void
    {
        $this->assertSame(
            GetAiProviderStatusUseCase::STATE_DEGRADED,
            GetAiProviderStatusUseCase::stateFor($this->config(), $this->probe(latencyMs: GetAiProviderStatusUseCase::DEGRADED_LATENCY_MS + 1)),
        );
    }

    public function test_testing_is_reserved_for_the_client_and_never_derived(): void
    {
        // Guard the contract: the mapper must never emit a server-side testing state.
        $states = [
            $this->config(enabled: false),
            null,
            $this->config(provider: 'disabled'),
            $this->config(),
        ];
        foreach ($states as $i => $config) {
            $status = match ($i) {
                0 => $this->probe(),
                1 => $this->probe(),
                default => $this->probe(available: false),
            };
            $this->assertNotSame(
                GetAiProviderStatusUseCase::STATE_TESTING,
                GetAiProviderStatusUseCase::stateFor($config, $status),
            );
        }
    }
}
