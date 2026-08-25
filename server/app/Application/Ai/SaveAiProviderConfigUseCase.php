<?php

namespace App\Application\Ai;

use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig;
use App\Domain\Ai\ValueObjects\AiProviderCapabilities;
use App\Domain\Ai\ValueObjects\AiProviderProtocol;
use InvalidArgumentException;

/**
 * Persist provider configuration (TASK-P17-006, P18-001). API key semantics:
 * replace-only (send a new key) or remove-only (`remove_api_key: true`).
 * A saved key is NEVER echoed back — the response is masked by
 * GetAiProviderConfigUseCase and a safe hint is persisted at save time.
 *
 * Capability-driven validation (TASK-P18-001): required fields derive from
 * AiProviderCapabilities instead of scattered per-provider conditionals.
 */
final readonly class SaveAiProviderConfigUseCase
{
    public function __construct(
        private AiProviderConfigRepository $configs,
        private GetAiProviderConfigUseCase $get,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): array
    {
        $existing = $this->configs->get() ?? AiProviderConfig::defaults();

        // PATCH semantics: an omitted provider keeps what is persisted;
        // the legacy full-save path always sends one explicitly.
        $provider = isset($input['provider']) && is_string($input['provider']) && $input['provider'] !== ''
            ? $input['provider']
            : $existing->provider;
        $allowed = [
            AiProviderConfig::PROVIDER_DISABLED,
            AiProviderConfig::PROVIDER_OLLAMA,
            AiProviderConfig::PROVIDER_OPENAI,
            AiProviderConfig::PROVIDER_MOCK,
        ];
        if (! in_array($provider, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported AI provider.');
        }

        $capabilities = AiProviderCapabilities::for($provider);

        $protocol = isset($input['protocol']) && is_string($input['protocol']) && $input['protocol'] !== ''
            ? $input['protocol']
            : AiProviderProtocol::defaultFor($provider);
        if (! AiProviderProtocol::isValid($provider, $protocol)) {
            throw new InvalidArgumentException("Protocol {$protocol} is not supported for this provider.");
        }

        $apiKey = $existing->apiKey;
        if (($input['remove_api_key'] ?? false) === true) {
            $apiKey = null;
        } elseif (isset($input['api_key']) && is_string($input['api_key']) && trim($input['api_key']) !== '') {
            $apiKey = trim($input['api_key']);
        }

        if ($capabilities->requiresApiKey && $apiKey === null && ($input['remove_api_key'] ?? false) !== true) {
            throw new InvalidArgumentException('This provider requires an API key.');
        }

        $hint = $apiKey !== null ? self::hintFor($apiKey) : null;

        // PATCH semantics for `enabled` too: absent means "keep what is
        // stored"; on a fresh save the legacy default stays enabled=true.
        $hasStored = $this->configs->get() !== null;

        $config = new AiProviderConfig(
            provider: $provider,
            enabled: array_key_exists('enabled', $input) ? (bool) $input['enabled'] : ($hasStored ? $existing->enabled : true),
            model: isset($input['model']) && trim((string) $input['model']) !== '' ? trim((string) $input['model']) : null,
            baseUrl: isset($input['base_url']) && trim((string) $input['base_url']) !== '' ? rtrim(trim((string) $input['base_url']), '/') : null,
            apiKey: $apiKey,
            userId: $existing->userId,
            protocol: $protocol,
            credentialHint: $hint,
            lastVerifiedAt: $existing->lastVerifiedAt,
            lastStatus: $existing->lastStatus,
            lastErrorCode: $existing->lastErrorCode,
        );

        $this->configs->save($config);

        return $this->get->__invoke();
    }

    /** Safe, non-reversible display hint; never the secret itself. */
    public static function hintFor(string $key): string
    {
        // Canonical mask matches the long-standing runtime hint contract
        // ('…' + last 4) so the UI has exactly one display format.
        if (strlen($key) <= 4) {
            return '…';
        }

        return '…'.substr($key, -4);
    }
}
