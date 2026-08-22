<?php

namespace Tests\Unit;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AiProviderConfigRepository;
use App\Domain\Ai\Entities\AiProviderConfig as ConfigEntity;
use App\Infrastructure\Ai\AiProviderFactory;
use App\Infrastructure\Ai\ConfigAiProviderResolver;
use App\Infrastructure\Ai\Providers\MockProvider;
use App\Infrastructure\Ai\Providers\OllamaProvider;
use App\Infrastructure\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Contracts\Config\Repository as Config;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

class StoredAiProviderResolverTest extends TestCase
{
    private function factory(Config $config): AiProviderFactory
    {
        return new AiProviderFactory($config);
    }

    #[Test]
    public function saved_enabled_config_wins_over_env(): void
    {
        $configs = $this->createMock(AiProviderConfigRepository::class);
        $configs->method('get')->willReturn(new ConfigEntity(
            provider: 'openai',
            enabled: true,
            model: 'stored-model',
            baseUrl: 'https://stored.example/v1',
            apiKey: 'stored-key',
        ));
        $resolver = new ConfigAiProviderResolver(
            $this->factory($this->app->make('config')),
            $configs,
        );

        $provider = $resolver->resolve();
        $this->assertInstanceOf(OpenAiCompatibleProvider::class, $provider);
        $this->assertSame('stored-model', $provider->model());
        $base = new ReflectionProperty($provider, 'baseUrl');
        $this->assertSame('https://stored.example/v1', $base->getValue($provider));
    }

    #[Test]
    public function disabled_or_missing_config_falls_back_to_env(): void
    {
        // No stored config → env driver (mock).
        $none = $this->createMock(AiProviderConfigRepository::class);
        $none->method('get')->willReturn(null);
        $env = $this->app->make('config');
        $env->set('ai.driver', 'mock');
        $this->assertInstanceOf(MockProvider::class, new ConfigAiProviderResolver($this->factory($env), $none)->resolve());

        // Stored but disabled → env fallback still applies.
        $disabled = $this->createMock(AiProviderConfigRepository::class);
        $disabled->method('get')->willReturn(new ConfigEntity(provider: 'ollama', enabled: false));
        $this->assertInstanceOf(MockProvider::class, new ConfigAiProviderResolver($this->factory($env), $disabled)->resolve());
    }

    #[Test]
    public function ollama_saved_config_resolves_without_api_key(): void
    {
        $configs = $this->createMock(AiProviderConfigRepository::class);
        $configs->method('get')->willReturn(new ConfigEntity(
            provider: 'ollama',
            enabled: true,
            model: 'llama3.1',
            baseUrl: 'http://localhost:11434',
        ));
        $provider = new ConfigAiProviderResolver($this->factory($this->app->make('config')), $configs)->resolve();
        $this->assertInstanceOf(OllamaProvider::class, $provider);
        $this->assertSame('llama3.1', $provider->model());
    }

    #[Test]
    public function provider_contract_is_stable(): void
    {
        $this->assertContains(AiProvider::class, class_implements(OllamaProvider::class) ?: []);
    }
}