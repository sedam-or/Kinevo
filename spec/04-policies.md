# 04 — Rules and policy engine

Depends on: `02-architecture.md`, `03-module-system.md`. Requirement prefix: `POL`. Decision: D-09.

**Principle.** A business value is *data*, never a literal in code (PRD-R4). There is no rule
language, no scripting. A **policy** is a typed, bounded, versioned, effective-dated setting that a
module declares in its descriptor.

## 1. Model

| Concept | Definition |
|---|---|
| Policy key | `<module>.<area>.<name>`, lowercase, dot-separated, stable after release. |
| Type | One of: `int` (min,max), `bool`, `enum` (closed set), `duration_seconds` (int, min,max), `local_time` (`HH:MM`, 24 h), `bp` (basis points 0–10000), `text_key` (message key), `json` (typed by a registered schema; used only where a structure is unavoidable, e.g. `billing.plans`). |
| Scope | `instance` (set by `instance_admin`; applies to all accounts) or `account` (set by the owner; overrides instance value). Each key declares `scopes` = `[instance]`, `[account]` or `[instance, account]`. |
| Resolution | For account `A` at instant `t`: the account value with the greatest `effective_from ≤ t` if the key allows account scope and one exists; else the instance value likewise; else the compiled default from the registry. |
| Version | Append-only. Changing a value inserts a new row; rows are never updated or deleted (audit). |
| Snapshot | Any record that *commits* a decision (a schedule draft/run, an applied draft, a billing invoice, an AI run) stores `policy_snapshot` = the resolved value **and** `policy_version_id` of every key it consumed. Replaying the decision with the snapshot is deterministic. |
| Preset | A named bundle of values for several keys, defined in a template (`05-templates-and-setup.md`). Applying a preset writes ordinary policy versions. |

## 2. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| POL-01 | Every policy key is declared in exactly one module descriptor with type, bounds, default, scopes, `effective_dating` (`immediate` or `future_only`), `snapshot` (bool), `secret` (always false for policies; secrets are separate, D-19). | Registry unit test: no duplicate keys; every key has bounds. |
| POL-02 | `GET /api/v1/policies?scope=` returns resolved values with `source` (`default`/`instance`/`account`), `version_id`, `effective_from`, plus the declared type/bounds/label key so the UI can render a form without hard-coding. | Contract test. |
| POL-03 | `PUT /api/v1/policies/{key}` body `{ "value": …, "effective_from": RFC3339?, "scope": "instance"|"account" }` validates type and bounds server-side → 422 `policy_value_invalid` (`details.bounds`), unknown key 404 `policy_unknown`, wrong scope 403 `forbidden`, scope not allowed for key 422 `policy_scope_invalid`. | Table tests per type. |
| POL-04 | `effective_from` defaults to now. For `future_only` keys, a value earlier than now is rejected with 422 `policy_effective_in_past`. For `immediate` keys, `effective_from` must still be ≥ the latest existing version's `effective_from` for that scope (no rewriting history) → else 422 `policy_effective_order`. | Tests. |
| POL-05 | Policy changes write an `audit_log` row (`policy.changed`, key, old/new value, scope) and an outbox event `PolicyChanged`; caches invalidate on the event. | Test. |
| POL-06 | Decision records that consume policies store a snapshot (POL "Snapshot" above) and the replay function `resolve_with_snapshot` is used by explain/replay features; live resolution is never used when a snapshot exists. | Scheduler determinism test: change a policy after a draft; re-explain the draft; output unchanged. |
| POL-07 | `kinevo-kernel::policy` exposes typed accessors (`PolicyKey<T>`); handlers/use cases cannot read an untyped string key (CQ-05). Adding a literal where a policy exists fails the `check-no-literals` CI script. | Script test with a seeded violation. |
| POL-08 | Bounds are enforced again when loading from the database; a value outside current bounds (after an upgrade narrowed them) resolves to the compiled default and logs `policy_out_of_bounds` once per key per process (no value in the log). | Test. |

## 3. Registry

Source column: **L** = value taken from the legacy implementation (`legacy-v0:<path>`); **A** = architect
default, safe and **ratifiable by the owner** (a change is a one-line edit here plus a task note);
**OWNER** = value only the owner can supply; the feature is disabled until set.
"Snap" = stored in decision snapshots.

### 3.1 Scheduler (`scheduler`)

| Key | Type / bounds | Default | Scopes | Snap | Src | Notes |
|---|---|---|---|---|---|---|
| `scheduler.min_slot_minutes` | int 5–120 | 15 | inst, acct | yes | L `Domain/Scheduling/SlotCalculator.php` | Gaps shorter than this are not fillable. |
| `scheduler.reserve_bp` | bp 0–9000 | 3000 | inst, acct | yes | L `DraftInput.php` (30 %) | Share of the planning horizon that must stay unoccupied (SafetyReserve). |
| `scheduler.day_start` | local_time | 08:00 | inst, acct | yes | A (new, D-33) | Start of the daily planning window in the account timezone. |
| `scheduler.day_end` | local_time (> day_start) | 22:00 | inst, acct | yes | A (new, D-33) | End of the window (exclusive). Legacy behaved as 00:00–24:00; parity fixtures set `00:00`/`24:00`. |
| `scheduler.anchor.duration_minutes` | int 5–120 | 25 | inst, acct | yes | L `Rules/SacredAnchorRule.php` | Exact duration of the daily anchor. |
| `scheduler.anchor.earliest_time` | local_time | 06:00 | inst, acct | yes | L same | Earliest start of an anchor; the effective earliest is `max(this, day_start)`. |
| `scheduler.daily_capacity_bp` | bp 100–10000, nullable | null | inst, acct | yes | L `DraftInput.dailyCapacityPercent` (nullable 1–100 %) | Optional cap on scheduled minutes per day as a share of free minutes. Null = no cap. |
| `scheduler.default_task_minutes` | int 5–480 | 45 | inst, acct | yes | L `Application/Scheduling/AssembleScheduleInput.php` | Used when a task has no estimate. |
| `scheduler.draft_horizon_days` | int 1–28 | 7 | inst, acct | no | A | Length of a generated draft. |
| `scheduler.sync_max_horizon_days` | int 1–28 | 14 | inst | no | L `SyncNowUseCase.php` | Upper bound for Sync Now. |
| `scheduler.weekly_draft.enabled` | bool | true | inst, acct | no | A | |
| `scheduler.weekly_draft.weekday` | enum mon..sun | sun | inst, acct | no | A | Day the next week's draft is prepared. |
| `scheduler.weekly_draft.local_time` | local_time | 04:00 | inst, acct | no | L `bootstrap/app.php:31` | |
| `scheduler.run_lock_seconds` | duration 10–900 | 120 | inst | no | A | Per-account lock lease for generate/sync. |

### 3.2 Tasks, goals, execution (`tasks`, `goals`, `execution`)

| Key | Type / bounds | Default | Scopes | Snap | Src | Notes |
|---|---|---|---|---|---|---|
| `tasks.default_priority_tier` | int 1–3 | 2 | inst, acct | no | A (range L `PriorityTier.php`) | 1 = highest. |
| `tasks.max_subtasks` | int 1–200 | 50 | inst | no | A | |
| `tasks.title_max_chars` | int 40–500 | 200 | inst | no | A | |
| `goals.title_max_chars` | int 40–500 | 200 | inst | no | A | |
| `execution.focus.default_minutes` | int 5–180 | 25 | inst, acct | no | A | Default timer target for a session. |
| `execution.session.stale_after_minutes` | int 30–2880 | 720 | inst | no | A | A running session older than this is flagged `stale` and may be closed by the user. |
| `execution.now_window_grace_minutes` | int 0–60 | 5 | inst, acct | no | A | Tolerance when deciding "now" vs "next" around slot boundaries. |

### 3.3 Identity and security (`identity`, `admin`)

| Key | Type / bounds | Default | Scopes | Src | Notes |
|---|---|---|---|---|---|
| `auth.registration.mode` | enum `open`,`closed`,`invite` | closed | inst | A (template `cloud`: open) | Closed after first account (D-30). |
| `auth.password.min_length` | int 10–128 | 12 | inst | A | |
| `auth.access_token.ttl` | duration 60–3600 | 600 | inst | A | D-11. |
| `auth.refresh_token.absolute_ttl` | duration 1 d–90 d | 2592000 (30 d) | inst | L `config/sanctum.php:53` | |
| `auth.refresh_token.idle_ttl` | duration 1 h–30 d | 1209600 (14 d) | inst | A | |
| `auth.lockout.max_attempts` | int 3–20 | 5 | inst | L `AUTH_MAX_ATTEMPTS` (5/min) | Failed logins per (account, window). |
| `auth.lockout.window` | duration 60–3600 | 900 | inst | A | |
| `auth.lockout.duration` | duration 60–86400 | 900 | inst | A | |
| `auth.totp.required` | bool | false | inst | A | If true, accounts must enrol at next login. |
| `auth.refresh.grace` | duration 0–60 | 10 | inst | A | Window in which a concurrent duplicate refresh returns the same new pair (ID-FR-05). |
| `identity.purge.delay` | duration 0–30 d | 0 | inst | A | Delay between account deletion request and purge job. |
| `api.idempotency.ttl` | duration 1 h–7 d | 86400 | inst | A | Stored idempotent responses (API-70). |
| `auth.invite.ttl` | duration 1 h–30 d | 604800 | inst | A | |
| `auth.password_reset.ttl` | duration 5 m–24 h | 3600 | inst | A | Admin-issued link/token when SMTP is absent (D-27). |
| `ratelimit.auth_per_minute` | int 1–120 | 5 | inst | L `AppServiceProvider.php` | Per client address. |
| `ratelimit.api_per_minute` | int 10–6000 | 120 | inst | L same | Per account. |
| `ratelimit.ai_per_minute` | int 1–120 | 10 | inst | L same | |
| `ratelimit.upload_per_minute` | int 1–600 | 20 | inst | L same | |
| `ratelimit.sync_per_minute` | int 10–6000 | 120 | inst | L same (`reconcile`) | |
| `ratelimit.export_per_minute` | int 1–120 | 10 | inst | L same | |
| `audit.retention_days` | int 30–3650 | 365 | inst | A | |
| `modules.cache_ttl_seconds` | duration 1–300 | 10 | inst | A | |

### 3.4 Sync, AI, knowledge, canvas

| Key | Type / bounds | Default | Scopes | Src | Notes |
|---|---|---|---|---|---|
| `sync.ledger.retention_days` | int 7–365 | 90 | inst | L `config/offline.php` | |
| `sync.batch.max_operations` | int 1–200 | 50 | inst | L same | |
| `sync.operation.max_bytes` | int 1 KiB–1 MiB | 65536 | inst | L same | |
| `sync.request.max_bytes` | int 16 KiB–4 MiB | 524288 | inst | L same | |
| `ai.provider.kind` | enum `disabled`,`openai_compatible`,`ollama` | disabled | inst | L `config/ai.php` | |
| `ai.allow_user_keys` | bool | true | inst | A | BYOK. |
| `ai.provider.base_url` | text (https URL; http allowed only for loopback) | `http://localhost:11434` for kind `ollama`, `https://api.openai.com/v1` for `openai_compatible` | inst | L `config/ai.php` | Instance provider endpoint (admin-trusted; private hosts allowed). |
| `ai.provider.model` | text 1–100 | `llama3.1` (ollama) / `gpt-4o-mini` (openai_compatible) | inst | L same | |
| `ai.byok.allow_private_hosts` | bool | false | inst | A | If false, per-account provider URLs must resolve to public addresses (SSRF defence, SEC-15). |
| `ai.proposal.ttl` | duration 1 h–30 d | 604800 | inst | A | Pending proposals expire. |
| `ai.proposal.retention` | duration 1 d–365 d | 2592000 | inst | A | Decided/expired proposals are deleted after this. |
| `ai.breakdown.max_milestones` | int 1–30 | 12 | inst | A | Output schema bound. |
| `ai.breakdown.max_tasks_per_milestone` | int 1–30 | 15 | inst | A | Output schema bound. |
| `ai.request.timeout` | duration 5–300 | 30 | inst | L `config/ai.php` | |
| `ai.prompt.max_chars` | int 500–100000 | 8000 | inst | L same | |
| `ai.output.max_tokens` | int 100–32000, nullable | null | inst | L same (nullable) | |
| `ai.runs.per_day` | int 1–10000, nullable | null | inst, acct | L same (`max_requests_per_day`) | Null = unlimited. |
| `ai.allowance.monthly_runs` | int 0–100000 | **OWNER** | inst | OWNER | Cloud only; meaningful when `billing` is on. |
| `knowledge.note.max_bytes` | int 1 KiB–5 MiB | 262144 | inst | A | |
| `knowledge.search.max_results` | int 5–100 | 25 | inst | A | |
| `canvas.scene.max_bytes` | int 64 KiB–20 MiB | 5242880 | inst | A | |
| `canvas.file.max_bytes` | int 64 KiB–20 MiB | 2097152 | inst | A | Attachments inside a canvas. |

### 3.5 Platform and billing

| Key | Type / bounds | Default | Scopes | Src | Notes |
|---|---|---|---|---|---|
| `jobs.lease_seconds` | duration 10–3600 | 120 | inst | A | |
| `jobs.retry.max_attempts` | int 1–20 | 8 | inst | A | |
| `jobs.retry.base_seconds` | duration 1–600 | 5 | inst | A | Exponential backoff base, doubled per attempt, capped at `jobs.retry.cap_seconds` |
| `jobs.retry.cap_seconds` | duration 60–86400 | 3600 | inst | A | Upper bound of a single backoff delay. |
| `jobs.schedule.daily_seconds` | duration 3600–2592000 | 86400 | inst | A | `[ASSUMED]` Daily module schedules use 86400 seconds; bounded from one hour to 30 days for operator adjustment. |
| `jobs.schedule.hourly_seconds` | duration 900–86400 | 3600 | inst | A | `[ASSUMED]` Hourly module schedules use 3600 seconds; bounded from 15 minutes to one day for operator adjustment. |
| `db.connect.retries` | int 0–30 | 10 | bootstrap only (config/env) | A | Not a stored policy; listed for completeness. |
| `export.max_age_days` | int 1–30 | 7 | inst | A | Generated export archives are deleted after this. |
| `billing.currency` | text ISO-4217 | **OWNER** | inst | OWNER | Billing cannot be enabled until set (D-18). |
| `billing.plans` | json (schema `billing_plans_v1`) | **OWNER** | inst | OWNER | Plan ids, price in minor units, interval, limits (`max_workspaces`, `ai_monthly_runs`, …). |
| `billing.grace_days` | int 0–60 | **OWNER** | inst | OWNER | Days past due before downgrade. |
| `billing.gateway` | enum `fake` | `fake` | inst | A | Active payment adapter; real adapters are added by their own tasks after OWNER-04. |
| `billing.retain_invoice_totals` | bool | false | inst | A | Keep anonymised invoice totals after account deletion; OWNER-05 decides whether law requires it. |

Per-module detail (semantics, validation, effects) is in `modules/*.md`; this registry is the single list of keys. A module spec may not mention a key that is not here.

## 4. Change flow (state machine summary)

```
PUT /policies/{key}
   │ validate scope/permission → validate type & bounds → check effective rules
   ├─ fail → 4xx error (registry) ; nothing written
   └─ ok → [tx] insert policy_versions + audit_log + outbox(PolicyChanged) → 200 {version_id, effective_from}
              └─ cache invalidation on event (≤ 1 s same node; TTL bound elsewhere)
```

Failure/recovery: if the transaction fails (database error) the response is 503 `service_unavailable`; the client retries with the same `Idempotency-Key` (`07-api-conventions.md` §8); no partial state exists.

## 5. Open items for the owner (not guessed)

| ID | Item | Blocks |
|---|---|---|
| OWNER-01 | Billing currency, plans, prices, grace period | `billing` module enablement, M9 |
| OWNER-02 | Hosted AI monthly allowance per plan | M9 |
| OWNER-03 | Confirm the **A** defaults above (they ship as-is if unchallenged) | none |
