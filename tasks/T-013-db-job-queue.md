---
id: T-013
title: Add the PostgreSQL job queue (enqueue, claim, complete, retry, recurring ticks)
milestone: M1
kind: feature
status: in_progress
depends_on: [T-012]
requirements: [D-13, ARCH-04, ADR-0009, MOD-05, POL-05, TST-20]
branch: m1-t013-db-job-queue
budget_lines: 400
assignee: "Codex"
---

# T-013: Add the PostgreSQL job queue (enqueue, claim, complete, retry, recurring ticks)

## Goal

Modules enqueue jobs inside their transaction; a worker claims due jobs with `FOR UPDATE SKIP LOCKED`, runs idempotent handlers, retries with capped exponential backoff, and re-claims jobs whose lease expired. Recurring jobs are materialised once per slot.

## Context

> **Jobs (`02-architecture.md` §7, D-13)**: Table `jobs` is the queue. Workers claim with `SELECT ... WHERE state='queued' AND run_at <= now() ORDER BY run_at, id FOR UPDATE SKIP LOCKED LIMIT n`, then `UPDATE state='running', locked_until = now() + lease`. A job handler is idempotent. A crashed worker's lease expires (`jobs.lease_seconds`) and the job is re-claimed. Recurring jobs are defined by modules as `JobSchedule { kind, cadence_policy_key }` and materialised by a leader-less "tick" job: each tick inserts the next occurrence with a unique key `(kind, account_id, scheduled_for)` using `ON CONFLICT DO NOTHING`. Cadences are policies, never constants. `now()` for job scheduling comes from the injected `Clock`.
> **Schema** (`06` §3): `jobs(id, account_id, kind, unique_key, payload jsonb, state in queued|running|succeeded|failed|dead, run_at, attempts, max_attempts, locked_by, locked_until, last_error_code, created_at, finished_at, UNIQUE(kind, unique_key))`, index `jobs_claim (run_at, id) WHERE state='queued'`.
> **Grants** (T-012): `kinevo_app` may SELECT and INSERT `jobs` (enqueue inside a `Tx`); `kinevo_system` may SELECT, INSERT, UPDATE (claim and bookkeeping in `SystemTx`). No RLS on `jobs`.
> **Retry policy keys** (`04-policies.md` §3.5): `jobs.lease_seconds` (10–3600, default 120), `jobs.retry.max_attempts` (1–20, 8), `jobs.retry.base_seconds` (1–600, 5; "doubled per attempt, capped at `jobs.retry.cap_seconds`"), `jobs.retry.cap_seconds` (60–86400, 3600). These are passed in by the caller as values resolved from policies; `kinevo-db` has no policy dependency.
> **MOD-05**: Jobs of a disabled module are not claimed (their rows stay `queued`); on re-enable they run. Recurring schedules of a disabled module are not materialised.
> **Dead-state visibility** (`admin.md` §2): `GET /admin/jobs/summary` returns counts by kind/state and oldest queued age; ADM-FR-05 audits retries; `jobs` failing for good become `dead`.

## Scope

**In:**
- `kinevo_db::jobs`:
  - `NewJob { account_id: Option<AccountId>, kind: JobKind, unique_key: Option<String>, payload: serde_json::Value, run_at: Instant, max_attempts: u8 }` where `JobKind` is a validated `&'static str` newtype (`^[a-z][a-z0-9_.]{1,63}$`, e.g. `account.purge`).
  - `enqueue(conn: &mut impl TxConn, ids: &dyn IdGen, now: Instant, job: NewJob) -> Result<EnqueueOutcome, AppError>` (`Inserted(JobId)` or `Duplicate` when `(kind, unique_key)` already exists, via `ON CONFLICT DO NOTHING`).
  - `claim(tx: &mut SystemTx, worker: &WorkerId, now: Instant, lease_seconds: i64, limit: u16, enabled_kinds: &[JobKind]) -> Result<Vec<ClaimedJob>, AppError>`: selects `queued` rows whose kind is in `enabled_kinds` (MOD-05), plus `running` rows whose `locked_until < now` (crash recovery, counted as an attempt), ordered `run_at, id`, `FOR UPDATE SKIP LOCKED`, sets `running`, `locked_by`, `locked_until`, increments `attempts`.
  - `complete(tx, id, now)` → `succeeded`; `fail(tx, id, now, code: ErrorCode, retry: RetryPolicy)` → back to `queued` with `run_at = now + min(cap, base · 2^(attempts−1))` and `last_error_code`, or `dead` once `attempts ≥ max_attempts`; `extend_lease(tx, id, now, lease_seconds)` for long handlers.
  - `RetryPolicy { base_seconds, cap_seconds }` and the pure function `backoff_seconds(attempts, policy) -> i64` (overflow-safe).
  - `JobSchedule` materialisation helper `materialise(tx, ids, now, schedules: &[ScheduleSlot])` where `ScheduleSlot { kind, account_id, scheduled_for: Instant }` inserts with `unique_key = "{scheduled_for}"`.
  - `summary(conn) -> Vec<JobCount { kind, state, count }>` and `oldest_queued_age_seconds` for the admin endpoint.
- Handler contract types only: `JobHandler` trait (`#[async_trait]`, object-safe: `fn kind(&self) -> JobKind; async fn run(&self, ctx: JobContext, job: ClaimedJob) -> Result<(), AppError>`; add `async-trait` to `kinevo-db`) so modules can implement it. The worker loop that calls handlers is T-039.

**Out:**
- The worker process, dispatcher loop, graceful shutdown: T-039.
- Module job handlers and tick definitions: their tasks.
- Admin job summary endpoint: T-038.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/src/jobs.rs` | create | everything above |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod jobs;` |
| `crates/kinevo-db/tests/jobs.rs` | create | component and concurrency tests |

## Interfaces

```rust
pub struct JobKind(&'static str);
pub struct ClaimedJob { pub id: JobId, pub account_id: Option<AccountId>, pub kind: String, pub payload: serde_json::Value, pub attempts: i32, pub max_attempts: i32 }
pub enum EnqueueOutcome { Inserted(JobId), Duplicate }
pub fn backoff_seconds(attempts: u32, p: RetryPolicy) -> i64;   // min(cap, base * 2^(attempts-1))
pub async fn enqueue(conn: &mut impl TxConn, ids: &dyn IdGen, now: Instant, job: NewJob) -> Result<EnqueueOutcome, AppError>;
pub async fn claim(tx: &mut SystemTx, worker: &WorkerId, now: Instant, lease_seconds: i64, limit: u16, enabled: &[JobKind]) -> Result<Vec<ClaimedJob>, AppError>;
```

## Business rules

1. A job whose lease expired is claimable again and the claim counts as an attempt; at `attempts ≥ max_attempts` it becomes `dead` instead of running again.
2. Backoff doubles per attempt: with base 5 and cap 3600, attempts 1,2,3,10 give 5, 10, 20, 2560; attempt 11 gives 3600 (capped).
3. Two workers calling `claim` concurrently never receive the same job (`SKIP LOCKED`).
4. `Duplicate` is a success for callers (idempotent enqueue), not an error.
5. A job inserted in a transaction that rolls back never exists.

## Security

- Payloads hold ids and enums only (DOM-09); `enqueue` rejects a payload whose serialised size exceeds 16 KiB and one containing any JSON string longer than 128 characters (a cheap guard against content leaking into the queue).
- Handlers receive `account_id` and must open `Tx::begin(account_id)` themselves (ADR-0011); the queue grants no tenant access.

## Implementation notes

- `run_at <= now` uses the injected `now` as a bound parameter, never SQL `now()`, so tests control time.
- Single `UPDATE … WHERE id IN (SELECT … FOR UPDATE SKIP LOCKED) RETURNING …` statement keeps the claim atomic.
- Use `i64` seconds; compute `2^(n-1)` with checked shifts and saturate.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** base 5, cap 3600 **When** `backoff_seconds` is called for attempts 1,2,3,10,11,200 **Then** 5,10,20,2560,3600,3600 | `jobs::tests::pol_backoff_doubles_and_caps` |
| AC-2 | **Given** two enqueues with the same `(kind, unique_key)` **When** executed **Then** the second returns `Duplicate` and one row exists | `jobs::enqueue_is_idempotent_by_unique_key` |
| AC-3 | **Given** a job enqueued in a rolled-back `Tx` **When** the table is read **Then** it is absent | `jobs::enqueue_rolls_back_with_tx` |
| AC-4 | **Given** 20 queued jobs and 4 concurrent `claim(limit 5)` calls **When** they finish **Then** 20 distinct jobs were claimed, none twice | `jobs::tst_20_concurrent_claims_are_disjoint` |
| AC-5 | **Given** a running job whose lease expired **When** claimed at `now` **Then** it is returned with `attempts` incremented; with `attempts = max` it becomes `dead` instead | `jobs::lease_expiry_reclaims_and_caps_attempts` |
| AC-6 | **Given** `fail` with the policy **When** called twice **Then** `run_at` moves by 5 s then 10 s from the injected now and `last_error_code` is stored | `jobs::fail_schedules_backoff_from_injected_clock` |
| AC-7 | **Given** jobs of kinds `a` and `b` and `enabled = [a]` **When** claimed **Then** only `a` is returned and `b` stays queued | `jobs::mod_05_disabled_module_jobs_not_claimed` |
| AC-8 | **Given** a payload with a 200-character string **When** enqueued **Then** the call fails without inserting | `jobs::payload_guard_rejects_content_like_strings` |
| AC-9 | **Given** the same schedule slot materialised twice **When** executed **Then** one job exists | `jobs::materialise_is_idempotent_per_slot` |

## Tests

```
cargo nextest run -p kinevo-db --features testing   # environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
