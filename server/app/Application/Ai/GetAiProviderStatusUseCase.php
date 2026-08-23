<?php
namespace App\Application\Ai;
use App\Domain\Ai\AiOrchestrator;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\ValueObjects\AiProviderStatus;

/**
 * Provider health snapshot — THE single source of truth for AI status
 * (TASK-P17-007, FR-60). Derives one canonical `state` from the saved
 * configuration plus a live reachability probe; every surface (settings UI,
 * status endpoint) MUST render from this mapping instead of re-interpreting
 * raw fields.
 *
 * States:
 * - disabled:       AI off by explicit decision (user-saved or deployment).
 * - not_configured: enabled provider missing required setup (e.g. an OpenAI
 *                   key) — configured intent, unusable without it.
 * - testing:        client-side transient during a connection test; the
 *                   server never returns it, the contract reserves it.
 * - connected:      reachable within the latency budget.
 * - degraded:       reachable but slower than the budget.
 * - unavailable:    probe failed (unreachable / HTTP error).
 *
 * `configured ≠ available` shows up as degraded/unavailable while enabled,
 * and as `configured` when the probe itself errored (unverified snapshot).
 */
final class GetAiProviderStatusUseCase
{
    public const STATE_DISABLED = 'disabled';
    public const STATE_NOT_CONFIGURED = 'not_configured';
    public const STATE_CONFIGURED = 'configured';
    public const STATE_TESTING = 'testing';
    public const STATE_CONNECTED = 'connected';
    public const STATE_DEGRADED = 'degraded';
    public const STATE_UNAVAILABLE = 'unavailable';

    /** Latency above this (ms) counts as degraded while still usable. */
    public const DEGRADED_LATENCY_MS = 2000;

    public function __construct(
        private AiOrchestrator $ai,
        private AiProviderConfigRepository $configs,
    ) {}

    /**
     * @return array{provider: string, model: string, state: string, enabled: bool, available: bool, latency_ms: int|null, error: string|null}
     */
    public function __invoke(): array
    {
        $config = $this->configs->get();
        try {
            $status = $this->ai->status();
        } catch (\Throwable) {
            // A failing probe must not break the snapshot: report the saved
            // configuration as unverified rather than guessing availability.
            $status = new AiProviderStatus(
                $config === null ? 'disabled' : $config->provider,
                $config === null ? '' : ($config->model ?? ''),
                false,
                null,
                null,
            );
            return $this->snapshot($status, self::STATE_CONFIGURED, $config);
        }

        return $this->snapshot($status, self::stateFor($config, $status), $config);
    }

    /**
     * Canonical mapping — pure, unit-testable, reused by the masked config
     * payload so both endpoints can never disagree.
     */
    public static function stateFor(?AiProviderConfig $config, AiProviderStatus $status): string
    {
        // An explicit user decision wins over whatever the deployment resolves.
        if ($config !== null && (! $config->enabled || $config->provider === AiProviderConfig::PROVIDER_DISABLED)) {
            return self::STATE_DISABLED;
        }
        if ($config === null) {
            // No saved preference: the deployment default applies.
            // A disabled/unresolved deployment means AI is simply off.
            $deploymentOff = $status->provider === 'disabled' || $status->provider === '';
            return $deploymentOff ? self::STATE_DISABLED : self::stateForEnabled($status);
        }
        // Enabled provider that can never work without its credential is
        // NOT configured — distinct from reachable-but-down (unavailable).
        if ($config->provider === AiProviderConfig::PROVIDER_OPENAI && $config->apiKey === null) {
            return self::STATE_NOT_CONFIGURED;
        }
        return self::stateForEnabled($status);
    }

    private static function stateForEnabled(AiProviderStatus $status): string
    {
        if (! $status->available) {
            return self::STATE_UNAVAILABLE;
        }
        if (($status->latencyMs ?? 0) > self::DEGRADED_LATENCY_MS) {
            return self::STATE_DEGRADED;
        }
        return self::STATE_CONNECTED;
    }

    /**
     * @return array{provider: string, model: string, state: string, enabled: bool, available: bool, latency_ms: int|null, error: string|null}
     */
    private function snapshot(AiProviderStatus $status, string $state, ?AiProviderConfig $config): array
    {
        return [
            'provider' => $status->provider,
            'model' => $status->model,
            'state' => $state,
            'enabled' => $config !== null && $config->enabled && $config->provider !== AiProviderConfig::PROVIDER_DISABLED,
            'available' => $status->available,
            'latency_ms' => $status->latencyMs,
            'error' => $status->error,
        ];
    }
}
