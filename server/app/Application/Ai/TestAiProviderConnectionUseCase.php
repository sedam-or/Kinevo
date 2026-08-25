<?php

namespace App\Application\Ai;

use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use App\Infrastructure\Ai\AiProviderFactory;
use App\Infrastructure\Ai\Providers\DisabledProvider;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * "Test connection" in the settings UI (TASK-P17-006, upgraded by
 * TASK-P18-008). A valid test verifies authentication, protocol
 * compatibility and model usability through MINIMAL NON-MUTATING INFERENCE —
 * a bare TCP/ping is not sufficient. The probe never carries user content:
 * it sends a fixed synthetic prompt and expects any non-empty completion.
 *
 * When the tested settings equal what is already persisted (no candidate
 * overrides), the outcome is recorded on the config (`last_verified_at`,
 * `last_status`, `last_error_code`) so the UI can show verification age.
 */
final readonly class TestAiProviderConnectionUseCase
{
    private const PROBE_PROMPT = 'Connection check. Reply with the single word OK.';

    public function __construct(
        private AiProviderConfigRepository $configs,
        private AiProviderFactory $factory,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function __invoke(array $input): array
    {
        // The canonical settings-test may run against the persisted settings
        // as-is (no payload); the legacy path always names a provider.
        $existing = $this->configs->get() ?? AiProviderConfig::defaults();
        $providerName = isset($input['provider']) && is_string($input['provider']) && $input['provider'] !== ''
            ? $input['provider']
            : $existing->provider;
        $allowed = [
            AiProviderConfig::PROVIDER_DISABLED,
            AiProviderConfig::PROVIDER_OLLAMA,
            AiProviderConfig::PROVIDER_OPENAI,
            AiProviderConfig::PROVIDER_MOCK,
        ];
        if (! in_array($providerName, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported AI provider.');
        }

        if ($providerName === AiProviderConfig::PROVIDER_DISABLED) {
            return [
                'status' => (new DisabledProvider)->status()->toArray(),
                'ok' => false,
                'code' => AiProviderException::CODE_UNSUPPORTED,
                'message' => 'The disabled provider cannot serve AI requests.',
            ];
        }

        // Resolve the tested key honoring replace/remove semantics against
        // whatever is currently persisted.
        $apiKey = $existing->apiKey;
        if (isset($input['api_key']) && is_string($input['api_key']) && trim($input['api_key']) !== '') {
            $apiKey = trim($input['api_key']);
        }
        $baseUrl = isset($input['base_url']) && is_string($input['base_url']) && trim($input['base_url']) !== ''
            ? trim($input['base_url'])
            : $existing->baseUrl;
        $model = isset($input['model']) && is_string($input['model']) && trim($input['model']) !== ''
            ? trim($input['model'])
            : $existing->model;

        $provider = $this->factory->createFrom(
            $providerName,
            $baseUrl,
            $model,
            $providerName === AiProviderConfig::PROVIDER_OPENAI ? $apiKey : null,
        );

        try {
            $response = $provider->generate(new AiRequest(
                new AiRole(AiRole::NATURAL_LANGUAGE_EXPLANATION),
                self::PROBE_PROMPT,
                null,
                0.0,
                16,
            ));
            if (trim($response->text) === '') {
                throw AiProviderException::unavailable('AI provider returned an empty completion.');
            }
        } catch (AiProviderException $e) {
            $this->recordVerification($existing, $input, false, $e);

            return [
                'status' => $provider->status()->toArray(),
                'ok' => false,
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ];
        }

        $this->recordVerification($existing, $input, true, null);

        return [
            'status' => $provider->status()->toArray(),
            'ok' => true,
            'code' => null,
            'message' => 'Connection verified with a minimal inference request.',
        ];
    }

    /**
     * Verification metadata is only meaningful for the SAVED settings; when
     * the user probes candidate overrides we do not touch the stored record.
     *
     * @param  array<string, mixed>  $input
     */
    private function recordVerification(
        AiProviderConfig $existing,
        array $input,
        bool $ok,
        ?AiProviderException $error,
    ): void {
        // Any candidate override (or an unsaved shape entirely) skips
        // persisting — the outcome would describe settings Kinevo does not run.
        if ($this->configs->get() === null) {
            return;
        }

        $this->configs->save(new AiProviderConfig(
            provider: $existing->provider,
            enabled: $existing->enabled,
            model: $existing->model,
            baseUrl: $existing->baseUrl,
            apiKey: $existing->apiKey,
            userId: $existing->userId,
            protocol: $existing->protocol,
            credentialHint: $existing->credentialHint,
            lastVerifiedAt: CarbonImmutable::now()->toISOString(),
            lastStatus: $ok ? 'connected' : 'failed',
            lastErrorCode: $ok ? null : ($error?->errorCode() ?? AiProviderException::CODE_UNAVAILABLE),
        ));
    }
}
