# Kinevo — AI Architecture

> STATUS: AUTHORITATIVE (P29 2026-08-31). Canonical AI architecture authority
> (provider abstraction ADR-011, metering, BYOK ledger). Commercial meaning of AI
> allowances: `docs/product/commercial-model.md`.

### Position
AI is optional intelligence assistance. Core product correctness MUST NOT depend on an LLM.

### Provider abstraction
```text
AIOrchestrator
   ↓
AIProvider interface
   ├── OllamaProvider
   ├── ExternalProvider (optional)
   └── MockProvider
```

### AI roles
Allowed first-class roles:
- goal decomposition;
- milestone proposal;
- note summarization;
- task extraction;
- concept extraction;
- knowledge relation suggestion;
- canvas proposal;
- natural language explanation of existing deterministic results.

### Explicitly prohibited AI authority
AI MUST NOT directly own:
- final schedule placement;
- access control;
- task deletion;
- state transition bypass;
- database mutation without domain validation.

### Structured output
All AI mutation proposals MUST be schema-constrained and validated.

Example:
```json
{
  "type": "goal_breakdown_proposal",
  "goal_id": "uuid",
  "rationale": "why this decomposition (optional, decision summary)",
  "assumptions": ["what the plan assumes (optional)"],
  "inputs": ["which inputs were used — deadline, capacity (optional)"],
  "constraints": ["which constraints were honoured (optional)"],
  "risks": ["what could go wrong (optional)"],
  "milestones": [
    {
      "title": "Literature Review",
      "target_date": "2026-09-10",
      "estimated_minutes": 2400
    }
  ]
}
```
### Explainability boundary (TASK-P17-027)
AI proposals MUST be explainable at a high level — decision summary,
assumptions, inputs used, constraints honoured — and MUST never expose
chain-of-thought or private reasoning (privacy §14/§15.4, design.md §44).

### Context selection
AI context MUST be minimal and relevant. Do not send the entire database by default.

Context builder SHOULD enforce:
- ownership;
- relevance;
- size budget;
- field allowlist;
- sensitive field exclusion.

### Privacy
Local Ollama is preferred where privacy benefits justify operational complexity. External AI providers are opt-in capabilities and MUST have explicit configuration.

### Provider settings & secret handling (design.md §104 / TASK-P17-006)
`Settings → AI & Providers` is the control plane and the ONLY surface for
provider configuration. Documented behavior:
- Exposes provider (provider abstraction above), connection status, model,
  base URL, API key (masked), test connection, enable/disable, privacy note.
- API key rules: never stored in browser storage; never returned raw to any
  client payload; encrypted server-side; masked after save; replace/remove only.
- Ollama path requires no API key.
- Proposal review/edit/acceptance (TASK-P17-004): user edits of a pending
  goal-breakdown proposal revalidate through the SAME schema rules as AI
  output (PUT /api/v1/ai/proposals/{id}, decision becomes `edited`);
  acceptance still gates every domain mutation — nothing reaches milestones
  until the user accepts.
- Status derives from ONE source of truth (GET /api/v1/ai/status): Disabled /
  Not Configured / Configured / Testing / Connected / Unavailable / Degraded.
  The UI distinguishes *configured* ≠ *available* (TASK-P17-007).

Implementation (TASK-P17-006): configuration persists in a single-row
`ai_provider_configs` table (MVP is single-user; global control plane, not
user-scoped). The API key column is encrypted with the application key
(`Crypt`) and excluded from every serialization; GET/PUT `/api/v1/ai/config`
return only `has_api_key` plus a `…last4` hint, and POST
`/api/v1/ai/config/test` pings candidate settings before saving. A saved,
enabled, non-disabled configuration takes precedence over environment
defaults (`ConfigAiProviderResolver`).

### Runtime control plane & resolution order (Phase 18 / TASK-P18-001..024)
Two distinct "AIs" exist in this project and MUST NOT be confused:
- **Coding-agent AI** — the local/remote model driving development (see
  AGENTS.md "Local developer tooling"). Never part of the product.
- **Kinevo runtime AI** — the provider configured at `Settings → AI &
  Providers`, used by product features (goal breakdown, explanations) under
  the rules in this document.

Canonical control-plane endpoints (legacy `/ai/config` delegates to the same
use cases; no second source of truth):
```text
GET   /api/v1/ai/settings            safe snapshot (masked, no secrets)
PATCH /api/v1/ai/settings            partial update; may precede credentials
POST  /api/v1/ai/settings/credential store/rotate encrypted key (atomic)
DELETE/api/v1/ai/settings/credential remove key only
POST  /api/v1/ai/settings/test       minimal non-mutating INFERENCE probe
POST  /api/v1/ai/settings/enable     requires configured+credentialed provider
POST  /api/v1/ai/settings/disable    first-class off state; config preserved
GET   /api/v1/ai/providers           capability catalog driving the UI fields
```

Credential flow: the key is encrypted server-side on write, a safe
non-reversible hint (`…last4`) is persisted so reads never decrypt, rotation
replaces the old credential atomically, and every response is masked.
Connection verification is NOT a ping: it runs one fixed synthetic probe
(never user content) through generate(); failures map to stable
`AI_PROVIDER_*` codes (UNAVAILABLE | AUTH_FAILED | BAD_CONFIGURATION |
MODEL_NOT_FOUND | TIMEOUT | RATE_LIMITED | UNSUPPORTED); transient upstream
errors are retried once inside the probe.

Resolution precedence (same inputs → same provider):
1. persisted single-row settings (saved enabled config wins);
2. environment deployment defaults (`AI_PROVIDER`, `AI_PROVIDER_BASE_URL`,
   `AI_PROVIDER_MODEL`, `AI_PROVIDER_API_KEY`);
3. application fallback: disabled — core works with AI off (FR-60).

Remote runtime without Ollama: any OpenAI-compatible endpoint works (e.g. an
LLM gateway). The dev stack reaches host gateways via the Docker bridge IP;
Ollama remains opt-in through compose profile `ai` (`make ollama-up`);
`make up/test/ci/e2e` never require it. Evidence:
`scripts/smoke-remote-runtime.sh` (injected `KINEVO_SMOKE_AI_API_KEY`,
nothing hardcoded) proves Browser/HTTP → Laravel → remote endpoint → model
call with Ollama stopped.

### Development models
Development tooling MAY use a high-context reasoning model such as Qwythos 9B Q6_K or a coding-specialized model. This is not part of runtime requirements.

### Production local model profile
A small quantized model is preferred for low-resource VPS. Exact model is a deployment tuning decision, not a domain requirement.

### AI audit
Persist safe metadata, not private prompts or note contents, unless explicit audit retention requires otherwise.

### AI usage & cost metering (Phase 25 / TASK-P25-001..005)
Every user inference is metered at the use-case layer (`AiCreditGuard`, not controllers):
- **Identity** — each run carries a `request_id` (uuid) for correlation across logs/support.
- **Preflight** — `begin(userId)` refuses the request (403 `ENTITLEMENT_LIMIT`) before any provider
  call when the plan's monthly `ai_credits` are exhausted.
- **Postflight** — `spend(userId)` consumes one credit only on success; provider failures record a
  `failed` run with `credits_consumed=0` and burn nothing.
- **Records** — `ai_runs` gains `request_id`, `credits_consumed`, and provider cost estimate columns
  (`estimated_cost_minor`, `cost_currency`, plus provenance `pricing_source`/`pricing_snapshot_id`).
- **Cost estimation** (P25-001, not a financial truth) — `AiCostEstimator` derives per-run cost from a
  versioned price catalog (`config/ai.php` `cost.catalog`: per-1K-token input/output rates in minor
  units + currency + effective window, `provider.*` wildcard). Catalog ships EMPTY (owner prices real
  providers); unpriced runs stay null. `estimated_cost ≠ provider invoice`; future pipeline:
  Usage → Estimated Cost → Actual Invoice → Gross Margin.
- CLI diagnostics (`ai:smoke`) call the orchestrator directly and never bill a user.
- **Provider routing & BYOK** (P25-006/008): resolution is user-scoped (`AiProviderResolver::resolve(userId)`).
  A per-user BYOK credential (`user_ai_provider_configs`, api key encrypted at rest; settings at
  `GET/PUT/DELETE /ai/byok`, gated by the per-plan `custom_provider` entitlement) wins over the global
  Kinevo-hosted config. Ledger split: BYOK runs spend **no** ai_credits and store **no** Kinevo cost
  (`billing_ledger=byok`); Kinevo-hosted runs spend one credit, cost the run against the price
  catalog, and mark `billing_ledger=kinevo`. Runtime safeguards (P25-007) bind BOTH paths.
- **Hard runtime safeguards** (P25-007, separate from credits): config-driven `ai.limits`
  (`AI_MAX_REQUESTS_PER_MINUTE` drives `throttle:ai`; `AI_MAX_REQUESTS_PER_DAY` and
  `AI_MAX_ESTIMATED_DAILY_COST` are enforced pre-provider by `AiCreditGuard`, 429
  `AI_DAILY_LIMIT`/`AI_DAILY_COST_LIMIT`); per-request context/output are already bounded by
  prompt budgets and `AiRequest.max_tokens`. Protect economics via credits; protect runtime via
  these — BYOK (P25-008) is still bound by them (no abuse bypass).
- **Usage surface** (P25-009, summary-first): `GET /ai/usage` aggregates the plan's `ai_credits`
  progress, Kinevo-hosted estimated cost + per-feature breakdown this month, BYOK request count, and
  unread alert events. No charts by design (daily chart deferred); the frontend `AiUsageSummaryCard`
  renders it in Settings → AI until a fuller Usage view lands.
- **Cost alerts** (P25-010, domain events first; channels later): after every metered success
  `AiCostAlertService` records events and never blocks:
  - `user.usage_threshold` (user-facing, in-app until dismissed via `GET/POST /ai/alerts[/read]`) —
    monthly ai_credits crossing 50/75/90/100% (config `ai.alerts.usage_thresholds`);
  - `ops.daily_cost` (ops, user_id NULL, never in user payloads) — company-wide estimated Kinevo
    spend crossing `AI_OPS_DAILY_COST_LIMIT`; logged + stored once per day;
  - `ops.user_anomaly` (ops, user-attributed) — a user crossing `AI_ANOMALY_DAILY_REQUESTS`
    requests/day; logged + stored once per day.
  Provider cost/price-anomaly detection is deferred (needs baselines). Delivery channels
  (email/Slack/notification center) are deliberately out of scope; this service only records events.

### Human approval
Material changes MUST follow:
```text
Propose → Preview → Accept/Edit/Reject → Validate → Commit
```

### AI failure behavior
If AI is unavailable:
- core app remains functional;
- deterministic scheduling remains functional;
- manual goal/task/knowledge workflows remain available.

### AI action surface audit matrix (TASK-P17-025)

Every AI capability answers the same six questions. Entry points live where
the object lives (P17-029); every mutation proposal is pending until an
explicit accept (FR-62); failures surface server-truth, never silent magic.

| Capability | Where invoked | Context sent | What changes on accept | Can edit | Can reject | Failure handling |
| ---------- | ------------- | ------------ | ---------------------- | -------- | ---------- | ---------------- |
| Goal breakdown | Goals list post-create suggestion; goal detail header; empty-milestone state (`goal-breakdown-ai` / `goal-detail-breakdown` / `milestones-empty-breakdown`) | Goal title + id; schema-constrained JSON prompt (no chain-of-thought) | Creates the proposed milestones on the goal | Yes — inline milestone edits revalidated through the same schema before acceptance (`proposal-edit`) | Yes — `proposal-reject`; nothing applied | Gate `ai-not-configured` for disabled/not_configured/unavailable states (server-truth state, P17-007/028); generation errors surface verbatim-safe (`goal-detail-generate-error`, suggestion error) |
| Note summarize | Note editor toolbar (`note-ai-summarize`) | Note content | Nothing — read-only summary panel (`note-ai-summary`); no domain mutation | n/a (display-only by design) | n/a — dismissing the panel changes nothing | Gate as above; request errors in `note-ai-error` |
| Note task extraction | Note editor toolbar (`note-ai-extract`) | Note content | Pending extraction review (`note-ai-extraction-proposal`); accepting creates Tasks only then (`note-ai-extract-accept`) | Yes — proposed tasks listed before accept | Yes — `note-ai-extract-reject` | Gate as above; errors in `note-ai-error` |
| Canvas structure suggestion | Canvas boards index (`canvas-suggest-prompt` / `canvas-suggest-submit`) | User prompt + canvas semantics | Pending sections proposal (`canvas-suggest-proposal`); accept creates the Canvas (`canvas-suggest-accept`) | Yes — sections reviewed pre-accept | Yes — `canvas-suggest-reject` | Gate as above; errors in `canvas-suggest-error` |
| Task clarify | Task detail (`task-detail-clarify`) | Task fields | Nothing — explanatory text only (`task-detail-clarify-result`) | n/a (display-only) | n/a — no mutation offered | Gate as above; errors in `task-detail-clarify-error` |

Audit notes:
- The shared gate intentionally treats `unavailable` like not-ready: one lazy
  status read gates every action (`generationReady = configured|connected`),
  so a dead provider never fires doomed requests (TASK-P17-028 posture).
  The notice routes to Settings where Test connection reveals the precise
  server-truth state.
- Display-only capabilities (summarize, clarify) are deliberately
  non-mutating: there is nothing to edit or reject because nothing is applied.
- Browser evidence: failure walk + reject path in
  `tests/e2e/tests/ai-action-audit.spec.ts`; success paths per row are proven
  by golden-journeys G2 (goal), NoteAiApiTest/CanvasAiApiTest (API level).

---

