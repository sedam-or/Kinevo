<?php

namespace App\Infrastructure\Ai\Providers;

use App\Domain\Ai\AiProviderException;
use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\ValueObjects\AiProviderStatus;
use App\Domain\Ai\ValueObjects\AiRequest;
use App\Domain\Ai\ValueObjects\AiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;

/**
 * External OpenAI-compatible provider transport (opt-in, explicit
 * configuration; docs/ai-architecture.md §Privacy). Uses the
 * /chat/completions contract. Transport failures surface as
 * AiProviderException so the app stays operational.
 */
final readonly class OpenAiCompatibleProvider implements AiProvider
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $model,
        private int $timeoutSeconds = 30,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return $this->model;
    }

    public function isAvailable(): bool
    {
        try {
            return $this->ping()->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function generate(AiRequest $request): AiResponse
    {
        $started = hrtime(true);

        $payload = [
            'model' => $this->model,
            'messages' => $this->messages($request),
            'stream' => false,
        ];
        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post($this->baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException) {
            throw AiProviderException::unavailable('AI provider is unreachable.');
        }

        if (! $response->successful()) {
            throw AiProviderException::unavailable(
                "AI provider returned HTTP {$response->status()}."
            );
        }

        $text = trim((string) ($response->json('choices.0.message.content') ?? ''));
        if ($text === '') {
            throw AiProviderException::unavailable('AI provider returned an empty response.');
        }

        $usage = $response->json('usage');

        return new AiResponse(
            $text,
            $this->name(),
            $this->model(),
            (int) ((hrtime(true) - $started) / 1_000_000),
            $usage['prompt_tokens'] ?? null,
            $usage['completion_tokens'] ?? null,
        );
    }

    public function status(): AiProviderStatus
    {
        $started = hrtime(true);

        try {
            $ping = $this->ping();
            $available = $ping->successful();
            $error = $available ? null : "AI provider returned HTTP {$ping->status()}.";
        } catch (ConnectionException) {
            $available = false;
            $error = 'AI provider is unreachable.';
        }

        return new AiProviderStatus(
            $this->name(),
            $this->model(),
            $available,
            (int) ((hrtime(true) - $started) / 1_000_000),
            $error,
        );
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(AiRequest $request): array
    {
        $messages = [];
        if ($request->systemPrompt !== null && $request->systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $request->systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $request->prompt];

        return $messages;
    }

    private function ping(): HttpResponse
    {
        return Http::timeout($this->timeoutSeconds)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->get($this->baseUrl.'/models');
    }
}
