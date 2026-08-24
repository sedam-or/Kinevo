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
 * Local Ollama transport (docs/ai-architecture.md). Uses the /api/generate
 * endpoint. Transport failures surface as AiProviderException so callers keep
 * the app operational (SRS §13.6).
 */
final readonly class OllamaProvider implements AiProvider
{
    public function __construct(
        private string $baseUrl,
        private string $model,
        private int $timeoutSeconds = 30,
    ) {}

    public function name(): string
    {
        return 'ollama';
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

        // json_encode([]) produces a JSON array, but Ollama requires options
        // to be a map — an empty list is rejected with HTTP 400 (found during
        // TASK-P17-032 real-provider verification; fakes never validated it).
        $options = $this->options($request);

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->post($this->baseUrl.'/api/generate', [
                    'model' => $this->model,
                    'prompt' => $this->composePrompt($request),
                    'stream' => false,
                    'options' => $options === [] ? new \stdClass : $options,
                ]);
        } catch (ConnectionException) {
            throw AiProviderException::unavailable('Ollama is unreachable.');
        }

        if (! $response->successful()) {
            throw AiProviderException::unavailable(
                "Ollama returned HTTP {$response->status()}."
            );
        }

        $text = trim((string) ($response->json('response') ?? ''));
        if ($text === '') {
            throw AiProviderException::unavailable('Ollama returned an empty response.');
        }

        return new AiResponse(
            $text,
            $this->name(),
            $this->model(),
            (int) ((hrtime(true) - $started) / 1_000_000),
            $response->json('prompt_eval_count'),
            $response->json('eval_count'),
        );
    }

    public function status(): AiProviderStatus
    {
        $started = hrtime(true);

        try {
            $ping = $this->ping();
            $available = $ping->successful();
            $error = $available ? null : "Ollama returned HTTP {$ping->status()}.";
        } catch (ConnectionException) {
            $available = false;
            $error = 'Ollama is unreachable.';
        }

        return new AiProviderStatus(
            $this->name(),
            $this->model(),
            $available,
            (int) ((hrtime(true) - $started) / 1_000_000),
            $error,
        );
    }

    private function ping(): HttpResponse
    {
        return Http::timeout($this->timeoutSeconds)->get($this->baseUrl.'/api/tags');
    }

    private function composePrompt(AiRequest $request): string
    {
        $system = $request->systemPrompt;
        if ($system === null || $system === '') {
            return $request->prompt;
        }

        return "System: {$system}\n\nUser: {$request->prompt}";
    }

    /**
     * @return array<string, mixed>
     */
    private function options(AiRequest $request): array
    {
        $options = [];
        if ($request->temperature !== null) {
            $options['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $options['num_predict'] = $request->maxTokens;
        }

        return $options;
    }
}
