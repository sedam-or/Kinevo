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

    /*
    |--------------------------------------------------------------------------
    | Hard runtime safeguards (TASK-P25-007)
    |--------------------------------------------------------------------------
    | Separate from ai_credits: credits protect entitlement/economics, these
    | protect the runtime (and apply to BYOK too — abuse protection is never
    | bypassed by bringing your own key). Null = no cap; numbers are NOT
    | locked by guesswork — set them explicitly via environment.
    |
    | Layers:
    |  - per-request : max_prompt_chars / max_system_prompt_chars above bound
    |                  context; provider output is capped by the request's
    |                  max_tokens (AiRequest) when supplied.
    |  - per-minute  : max_requests_per_minute drives the throttle:ai limiter.
    |  - per-day     : max_requests_per_day and max_estimated_daily_cost_minor
    |                  are enforced in the credit guard before a provider call.
    |  - per-period  : ai_credits (EntitlementService) — the economic layer.
    */
    'limits' => [
        'max_requests_per_minute' => env('AI_MAX_REQUESTS_PER_MINUTE'),
        'max_requests_per_day' => env('AI_MAX_REQUESTS_PER_DAY'),
        'max_estimated_daily_cost_minor' => env('AI_MAX_ESTIMATED_DAILY_COST'),
        // Per-request estimated budget (RESERVE layer, D-005): the configured
        // driver/model worst-case cost at the max token guards must stay below
        // this cap or the request is refused before any provider call.
        'max_request_budget_minor' => env('AI_MAX_REQUEST_BUDGET'),
        'max_input_tokens' => env('AI_MAX_INPUT_TOKENS'),
        'max_output_tokens' => env('AI_MAX_OUTPUT_TOKENS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Cost / Price Catalog (TASK-P25-001)
    |--------------------------------------------------------------------------
    | Drives Kinevo-hosted inference cost estimation only. Product data — the
    | OWNER MUST VERIFY real provider prices before relying on these for hard
    | cost caps (P25-007) or reporting (P25-010). Prices are integer minor
    | units per 1K tokens; a "provider.*" entry matches any model of that
    | provider. Absent/inactive entries => no cost estimated (run stays null).
    | BYOK runs (P25-008) are never costed here — the user bears that spend.
    | estimated_cost is NOT a financial truth (it ≠ the provider invoice).
    */
    'cost' => [
        'default_currency' => env('AI_COST_CURRENCY', 'USD'),
        'catalog' => [
            // Example versioned entries (COMMERCIAL PRICING DELTA D-004). Rates
            // are integer minor units (currency / 100) per `price_per_tokens`
            // tokens. verified=false: MUST be re-verified against the current
            // official provider source before any production economics.
            // 'openai.gpt-4o-mini' => [
            //     'currency' => 'USD',
            //     'input_price_minor' => 15,       // $0.15 / 1M tokens
            //     'output_price_minor' => 60,      // $0.60 / 1M tokens
            //     'cached_input_price_minor' => 15, // no verified cache discount
            //     'price_per_tokens' => 1_000_000,
            //     'effective_from' => '2026-01-01',
            //     'effective_until' => null,
            //     'pricing_version' => '2026.08.28-example',
            //     'source' => 'public list price',
            //     'verified' => false,
            // ],
            // 'deepseek.deepseek-chat' => [
            //     'currency' => 'USD',
            //     'input_price_minor' => 28,        // $0.28 / 1M (cache miss)
            //     'output_price_minor' => 42,       // $0.42 / 1M
            //     'cached_input_price_minor' => 3,  // $0.028 / 1M cache hit
            //     'price_per_tokens' => 1_000_000,
            //     'effective_from' => '2026-01-01',
            //     'effective_until' => null,
            //     'pricing_version' => '2026.08.28-example',
            //     'source' => 'public list price',
            //     'verified' => false,
            // ],
            // Falls back to none:
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Alerts (TASK-P25-010)
    |--------------------------------------------------------------------------
    | Domain events evaluated post-success on every metered run; delivery
    | channels are deliberately out of scope (no notification center yet).
    |   - usage_thresholds        [%] — per-user ai_credits thresholds that
    |                              raise a user-visible alert once each month.
    |   - ops_daily_cost_minor    int — company-wide estimated Kinevo spend per
    |                              day (minor units) that raises an OPS alert.
    |   - user_anomaly_daily_requests int — per-user request count per day that
    |                              raises an OPS anomaly alert.
    | Set any ops value to 0 / null to disable that check. ALERTS DO NOT BLOCK;
    | they only record events (the P25-007 hard limits still gate the runtime).
    */
    'alerts' => [
        'usage_thresholds' => [50, 75, 90, 100],
        'ops_daily_cost_minor' => (int) (env('AI_OPS_DAILY_COST_LIMIT') ?: 0),
        'user_anomaly_daily_requests' => (int) (env('AI_ANOMALY_DAILY_REQUESTS') ?: 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Cost Simulation (COMMERCIAL PRICING DELTA D-004 / P32-007)
    |--------------------------------------------------------------------------
    | Deterministic planning model used by `ai:cost-simulate`. FEATURE PROFILES
    | ARE DEPRECATED-BASELINE ASSUMPTIONS until real usage is instrumented
    | (P32/P37) — they exist so the quota decision has a starting economics
    | shape, NOT because they describe observed behaviour. Replace with
    | observed distributions before locking any quota. costs computed from the
    | versioned catalog above (config/ai.php `cost.catalog`).
    */
    'simulation' => [
        'cache_hit_ratio' => 0.5,
        'target_margin_low' => 0.30,
        'target_margin_high' => 0.50,
        'abuse_multiplier' => 15,
        'requests_per_month' => [
            'free' => ['mean' => 15, 'cv' => 0.6],
            'pro' => ['mean' => 60, 'cv' => 0.5],
            'power' => ['mean' => 120, 'cv' => 0.5],
        ],
        'features' => [
            'goal_breakdown' => ['input_tokens_mean' => 6000, 'cached_input_share' => 0.2, 'output_tokens_mean' => 1500, 'cv' => 0.5],
            'note_summary' => ['input_tokens_mean' => 4000, 'cached_input_share' => 0.2, 'output_tokens_mean' => 800, 'cv' => 0.5],
            'task_extraction' => ['input_tokens_mean' => 2500, 'cached_input_share' => 0.2, 'output_tokens_mean' => 600, 'cv' => 0.5],
            'planning' => ['input_tokens_mean' => 5000, 'cached_input_share' => 0.25, 'output_tokens_mean' => 1200, 'cv' => 0.5],
            'deep_analysis' => ['input_tokens_mean' => 12000, 'cached_input_share' => 0.3, 'output_tokens_mean' => 2500, 'cv' => 0.6],
            'wrapped_narrative' => ['input_tokens_mean' => 8000, 'cached_input_share' => 0.2, 'output_tokens_mean' => 2000, 'cv' => 0.5],
        ],
    ],
];
