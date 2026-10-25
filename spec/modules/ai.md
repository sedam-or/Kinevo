# Module `ai` (goal breakdown)

Kind: toggleable (default **off**). Depends on: `goals`, `tasks`. Tables: `ai_account_providers`, `ai_proposals`, `ai_runs`, `secrets` (shared). Prefix: `AI`.
Principle (D-31): **AI proposes; the user decides.** Output is untrusted input:
`user intent → provider → structured response → schema validation → domain validation → human approval → transaction`.
AI never changes the schedule and never calls tools or APIs.

## 1. Permissions

`ai.status.read`, `ai.breakdown.create`, `ai.proposal.read|update|accept|reject`, `ai.provider.manage` (own BYOK) — `account_owner`, `instance_admin`. Instance provider settings are policies + secrets (`admin` module).

## 2. Providers

| Kind | Endpoint (adapter) | Auth | Request mode |
|---|---|---|---|
| `openai_compatible` | `POST {base_url}/chat/completions` | `Authorization: Bearer <api key>` | `response_format: {type: "json_object"}`; sampling temperature is a fixed technical constant (`0.2`, recorded in `standards/open-source-stack.md` §AI), not a user setting in 1.0 |
| `ollama` | `POST {base_url}/api/chat` | none | `format: "json"`, `stream: false` |
| `disabled` | — | — | every call → `ai_not_configured` |

Resolution order per request: account BYOK (if `ai.allow_user_keys`) → instance provider (`ai.provider.*` + secret `ai.api_key`) → none.
HTTP client rules: timeout `ai.request.timeout`; redirects disabled; response body capped at 1 MiB; TLS verification on; for **account** URLs the resolved IP must not be loopback/private/link-local/metadata unless `ai.byok.allow_private_hosts` (re-checked on every request to defeat DNS rebinding by connecting to the validated address).

## 3. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /ai/status` | 200 `{ module_enabled, configured, source: "account"\|"instance"\|"none", kind?, model?, limits:{ runs_today, runs_per_day?, allowance_remaining? } }` | — |
| `POST /ai/goals/{goal_id}/breakdown:preview` `{ hint? }` | 200 `{ user_prompt_preview, char_count, data_fields:[…] }` — exactly what would be sent (the system prompt text is public in the repo and not repeated) | `not_found` |
| `POST /ai/goals/{goal_id}/breakdown` `{ hint?, max_milestones? }` + `Idempotency-Key` | 201 proposal | `ai_not_configured` 409, `ai_limit_reached` 429, `rate_limited` 429, `ai_provider_unavailable` 503, `ai_provider_timeout` 504, `ai_output_invalid` 422 (`details.reasons[]`), `goal_not_active` 409 |
| `GET /ai/proposals?status=&goal_id=` / `GET /ai/proposals/{id}` | 200 | `not_found` |
| `PUT /ai/proposals/{id}` (If-Match) `{ payload }` | 200 (re-validated) | `proposal_not_pending` 409, `ai_output_invalid` 422, `version_conflict` |
| `POST /ai/proposals/{id}/accept` `{ base_version }` + `Idempotency-Key` | 200 `{ milestone_ids[], task_ids[] }` | `proposal_not_pending` 409, `version_conflict`, `goal_not_active`, `workspace_archived` |
| `POST /ai/proposals/{id}/reject` | 204 | `proposal_not_pending` |
| `GET /ai/provider` | 200 `{ kind, base_url, model, key_last4 }` or 204 | — |
| `PUT /ai/provider` `{ kind, base_url, model, api_key? }` | 200 (masked) | `ai_byok_disabled` 403, `ai_host_not_allowed` 422, validation |
| `DELETE /ai/provider` | 204 | — |
| `POST /ai/provider/test` | 200 `{ ok, latency_ms }` or error | `ai_provider_unavailable`, `ai_provider_timeout` (rate-limited with `ratelimit.ai_per_minute`) |

## 4. Output schema `ai_breakdown_v1` (the only accepted proposal payload)

```
{ "schema": "ai_breakdown_v1",
  "milestones": [ { "title": str 1..200, "description"?: str ≤1000, "target_date"?: "YYYY-MM-DD",
                    "tasks": [ { "title": str 1..200, "estimate_minutes"?: int 5..480, "priority_tier"?: 1..3,
                                 "subtasks"?: [ str 1..200 ] (≤ 8) } ] (≤ ai.breakdown.max_tasks_per_milestone) } ]
                  (1 ≤ n ≤ ai.breakdown.max_milestones) }
```

## 5. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| AI-FR-01 | **Data minimisation.** The prompt contains only: goal title, goal description, goal target date, today's date (account zone), titles of existing milestones/tasks of that goal (≤ 50), the account locale, and the optional user hint. It never contains notes, canvases, landscape, other goals, email, or any identifier. `breakdown:preview` returns the exact user part. | Contract test asserts the field list; scan test for identifiers. |
| AI-FR-02 | **Prompt integrity.** The system prompt is a versioned constant (`prompt_version` stored on the run). User text is placed in a delimited block and labelled as data; the model is told to answer with the schema only. (Prompt-injection from the user's own text can only affect that user's own proposal; the output is validated and needs approval.) | Review + test with an injection string ⇒ still schema-valid or `ai_output_invalid`. |
| AI-FR-03 | **Parsing.** The response must be a single JSON object (a surrounding Markdown code fence is stripped; anything else ⇒ invalid). Size ≤ 64 KiB. | Table tests. |
| AI-FR-04 | **Schema validation** against `ai_breakdown_v1` with the policy bounds; unknown fields rejected. | Tests. |
| AI-FR-05 | **Domain validation**: every `target_date` ≥ today and ≤ the goal's `target_date` (when set); milestone dates non-decreasing in order; no duplicate milestone titles (case-insensitive) and none equal to an existing milestone title; text is NFC, control characters removed, HTML tags kept as literal text (rendered as text everywhere). Violations ⇒ 422 `ai_output_invalid` with `details.reasons[]` codes (never the raw output). | Tests. |
| AI-FR-06 | **No silent mutation.** Creating a proposal writes only `ai_proposals` + `ai_runs`. Milestones/tasks are created only by `accept`, in **one transaction**, through `CreateMilestonePort`/`CreateTaskPort` (the same domain validation as manual creation): milestones in order with `position`, tasks with `goal_id`, `milestone_id`, the goal's workspace, `priority_tier` default policy, `estimate_minutes` as given; subtasks created. (Legacy accept created milestones only.) | Test: accept ⇒ N milestones, M tasks, S subtasks; failure ⇒ none. |
| AI-FR-07 | **Editing before accepting** re-runs AI-FR-04/05 on the edited payload (including user-added items); only `pending` proposals can be edited. | Tests. |
| AI-FR-08 | **Limits.** Per account: `ratelimit.ai_per_minute`, `ai.runs.per_day` (null = unlimited), and, when `billing` is enabled, the plan's monthly allowance via `EntitlementPort`. Failed provider calls (timeout, 5xx) do **not** consume the daily/monthly run count; successful provider responses that fail validation **do** (the provider was used). | Tests. |
| AI-FR-09 | **Metering without content.** `ai_runs` stores kind, model, status, error code, input/output character counts, latency, `prompt_version` — never prompt or output text. Logs obey SEC-04. | Scan test over logs/DB for the test prompt text. |
| AI-FR-10 | **Degradation.** With `ai` disabled or unconfigured, no AI UI is shown except an explanatory "AI is not set up" state with a link to settings (admin) or a note (user). Core flows are unaffected. | Module-matrix test; UI test. |
| AI-FR-11 | **Secrets.** API keys are stored via `secrets` (AEAD), shown masked, never returned, never logged, and sent only to the configured host over TLS (or loopback http for Ollama). | Tests; SEC-AC-30. |
| AI-FR-12 | **Expiry.** Pending proposals expire after `ai.proposal.ttl` (job `ai.proposals.expire`); decided or expired proposals are deleted after `ai.proposal.retention` (job `ai.proposals.prune`). | Job tests. |
| AI-FR-13 | **Idempotency.** Creating a proposal with a repeated `Idempotency-Key` returns the stored proposal without calling the provider again; accepting twice returns the same ids. | Tests. |

## 6. State machine — proposal

| From | Event | Guard | To | Failure/recovery |
|---|---|---|---|---|
| (none) | breakdown OK | schema + domain valid | `pending` | invalid ⇒ no row; run recorded `invalid_output`; user may retry (counts per AI-FR-08) |
| `pending` | edit | valid | `pending` (version+1) | invalid ⇒ 422, unchanged |
| `pending` | accept | goal active; workspace not archived; `base_version` | `accepted` | any creation error ⇒ rollback, stays `pending` |
| `pending` | reject | — | `rejected` | — |
| `pending` | ttl elapsed | — | `expired` | user regenerates |
| `accepted`, `rejected`, `expired` | any | — | (terminal) | 409 `proposal_not_pending` |

## 7. Data flow — create proposal

1. Check module + permission; entitlement/limit check (read-only); acquire `ai` rate limit.
2. Resolve provider (account → instance); if none ⇒ `ai_not_configured`.
3. Build prompt from ports (`GoalReadPort`, `TaskReadPort`); enforce `ai.prompt.max_chars` (trim existing-item lists first, then reject `ai_prompt_too_large` 422).
4. Call provider (outside any DB transaction; no locks held).
5. Parse → schema → domain validation.
6. One transaction: insert `ai_runs` (+ increments), insert `ai_proposals`; commit. Respond 201.
The provider call never happens inside a database transaction.

## 8. Events / jobs

Emits `AiProposalCreated`, `AiProposalAccepted`, `AiProposalRejected`. Jobs: `ai.proposals.expire` (tick), `ai.proposals.prune` (daily).

## 9. Abuse cases

| ID | Case | Expected |
|---|---|---|
| SEC-AC-30 | BYOK URL `http://169.254.169.254/` or hostname resolving to a private IP | 422 `ai_host_not_allowed`; DNS-rebinding test: second resolution to private IP is refused at connect |
| SEC-AC-31 | Model returns 5 MB / nested JSON bomb | capped at 1 MiB body / 64 KiB payload ⇒ `ai_output_invalid` |
| SEC-AC-32 | Model output contains `<script>` in a title | stored as literal text; UI renders as text (XSS test on web) |
| SEC-AC-33 | Accept a proposal of another account | 404 |
| SEC-AC-34 | API key appears in `GET`/logs/audit | never (scan test) |
