<?php

namespace Tests\Unit;

use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiRole;
use App\Infrastructure\Ai\AiProviderFactory;
use App\Infrastructure\Ai\Providers\DisabledProvider;
use App\Infrastructure\Ai\Providers\MockProvider;
use App\Infrastructure\Ai\Providers\OllamaProvider;
use App\Infrastructure\Ai\Providers\OpenAiCompatibleProvider;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    private function request(): AiRequest
    {
        return new AiRequest(new AiRole('task_extraction'), 'Summarize this list.');
    }

    private function config(array $overrides = []): Config
    {
        $config = $this->app->make('config');
        foreach ($overrides as $key => $value) {
            $config->set($key, $value);
        }

        return $config;
    }

    #[Test]
    public function factory_resolves_configured_drivers(): void
    {
        $factory = new AiProviderFactory($this->config(['ai.driver' => 'mock']));
        $this->assertInstanceOf(MockProvider::class, $factory->create());

        $factory = new AiProviderFactory($this->config(['ai.driver' => 'disabled']));
        $this->assertInstanceOf(DisabledProvider::class, $factory->create());

        $factory = new AiProviderFactory($this->config([
            'ai.driver' => 'ollama',
            'ai.ollama.base_url' => 'http://localhost:11434',
            'ai.ollama.model' => 'llama3.1',
        ]));
        $this->assertInstanceOf(OllamaProvider::class, $factory->create());

        $factory = new AiProviderFactory($this->config([
            'ai.driver' => 'openai',
            'ai.openai.base_url' => 'https://api.openai.com/v1',
            'ai.openai.api_key' => 'secret',
        ]));
        $this->assertInstanceOf(OpenAiCompatibleProvider::class, $factory->create());
    }

    #[Test]
    public function mock_provider_returns_deterministic_output(): void
    {
        $provider = new MockProvider('mock-1');

        $this->assertTrue($provider->isAvailable());
        $response = $provider->generate($this->request());
        $this->assertStringContainsString('task_extraction', $response->text);
        $this->assertSame('mock', $response->provider);
        $this->assertTrue($provider->status()->available);
    }

    #[Test]
    public function disabled_provider_is_unavailable_and_fails_generation(): void
    {
        $provider = new DisabledProvider;

        $this->assertFalse($provider->isAvailable());
        $this->assertFalse($provider->status()->available);
        $this->assertSame('AI provider is disabled.', $provider->status()->error);

        try {
            $provider->generate($this->request());
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertSame('AI provider is disabled.', $e->getMessage());
        }
    }

    #[Test]
    public function ollama_provider_generates_and_reports_status(): void
    {
        Http::fake([
            'http://localhost:11434/api/tags' => Http::response([], 200),
            'http://localhost:11434/api/generate' => Http::response([
                'response' => 'Extracted tasks.',
                'prompt_eval_count' => 12,
                'eval_count' => 5,
            ], 200),
        ]);

        $provider = new OllamaProvider('http://localhost:11434', 'llama3.1');

        $this->assertTrue($provider->isAvailable());
        $this->assertTrue($provider->status()->available);

        $response = $provider->generate($this->request());
        $this->assertSame('Extracted tasks.', $response->text);
        $this->assertSame(12, $response->promptTokens);

        Http::assertSentCount(3);
    }

    #[Test]
    public function ollama_unreachable_is_reported_as_unavailable(): void
    {
        Http::fake([
            'http://localhost:11434/*' => Http::response(status: 500),
        ]);

        $provider = new OllamaProvider('http://localhost:11434', 'llama3.1');

        $this->assertFalse($provider->isAvailable());

        try {
            $provider->generate($this->request());
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }
    }

    #[Test]
    public function ollama_empty_response_is_unavailable(): void
    {
        Http::fake([
            'http://localhost:11434/api/generate' => Http::response(['response' => '   '], 200),
        ]);

        $provider = new OllamaProvider('http://localhost:11434', 'llama3.1');

        try {
            $provider->generate($this->request());
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringContainsString('empty', $e->getMessage());
        }
    }

    #[Test]
    public function openai_compatible_provider_generates_and_reports_status(): void
    {
        Http::fake([
            'https://api.openai.com/v1/models' => Http::response([], 200),
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Three milestones.']]],
                'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 8],
            ], 200),
        ]);

        $provider = new OpenAiCompatibleProvider('https://api.openai.com/v1', 'secret', 'gpt-4o-mini');

        $this->assertTrue($provider->status()->available);

        $response = $provider->generate($this->request());
        $this->assertSame('Three milestones.', $response->text);
        $this->assertSame(20, $response->promptTokens);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions')
                && $request->hasHeader('Authorization', 'Bearer secret');
        });
    }

    #[Test]
    public function provider_connection_exception_is_unavailable(): void
    {
        Http::fake([
            'http://localhost:11434/*' => fn () => throw new ConnectionException('refused'),
        ]);

        $provider = new OllamaProvider('http://localhost:11434', 'llama3.1');

        $this->assertFalse($provider->isAvailable());

        try {
            $provider->generate($this->request());
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringContainsString('unreachable', $e->getMessage());
        }
    }
}
