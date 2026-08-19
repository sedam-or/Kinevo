<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider Selection
    |--------------------------------------------------------------------------
    |
    | AI is optional intelligence assistance (docs/ai-architecture.md). Core
    | product correctness MUST NOT depend on an LLM. Selectable drivers:
    |   - ollama   local Ollama (default: localhost:11434)
    |   - openai   OpenAI-compatible external provider (opt-in, explicit config)
    |   - mock     deterministic canned responses (local/dev/testing)
    |   - disabled no provider; every request fails with AI_PROVIDER_UNAVAILABLE
    |
    | The application MUST remain operational when the configured provider is
    | unavailable (SRS FR-60, §13.6).
    |
    */

    'driver' => env('AI_PROVIDER', 'disabled'),

    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),

    'ollama' => [
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.1'),
    ],

    'openai' => [
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    ],

    'mock' => [
        'model' => env('AI_MOCK_MODEL', 'mock-1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Budget
    |--------------------------------------------------------------------------
    | AI context MUST be minimal and relevant (SRS §13.4). The prompt size cap
    | bounds a single request; the context builder enforces field allowlists.
    |
    */

    'max_prompt_chars' => (int) env('AI_MAX_PROMPT_CHARS', 8000),
    'max_system_prompt_chars' => (int) env('AI_MAX_SYSTEM_PROMPT_CHARS', 2000),
];
