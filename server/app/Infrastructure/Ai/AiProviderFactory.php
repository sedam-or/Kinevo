<?php

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Infrastructure\Ai\Providers\DisabledProvider;
use App\Infrastructure\Ai\Providers\MockProvider;
use App\Infrastructure\Ai\Providers\OllamaProvider;
use App\Infrastructure\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Contracts\Config\Repository as Config;
use InvalidArgumentException;

/**
 * Resolves the configured AI provider driver (config/ai.php). Selection is a
 * deployment decision; domain semantics never change (SRS FR-60, NFR-11).
 */
final readonly class AiProviderFactory
{
    public function __construct(
        private Config $config,
    ) {}

    public function create(): AiProvider
    {
        return match ($this->config->get('ai.driver')) {
            'ollama' => new OllamaProvider(
                rtrim((string) $this->config->get('ai.ollama.base_url', 'http://localhost:11434'), '/'),
                (string) $this->config->get('ai.ollama.model', 'llama3.1'),
                (int) $this->config->get('ai.timeout_seconds', 30),
            ),
            'openai' => new OpenAiCompatibleProvider(
                rtrim((string) $this->config->get('ai.openai.base_url', 'https://api.openai.com/v1'), '/'),
                (string) $this->config->get('ai.openai.api_key', ''),
                (string) $this->config->get('ai.openai.model', 'gpt-4o-mini'),
                (int) $this->config->get('ai.timeout_seconds', 30),
            ),
            'mock' => new MockProvider(
                (string) $this->config->get('ai.mock.model', 'mock-1'),
            ),
            'disabled' => new DisabledProvider,
            default => throw new InvalidArgumentException(
                "Unsupported AI provider driver: {$this->config->get('ai.driver')}"
            ),
        };
    }
}
