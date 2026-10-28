---
id: T-013
title: Add the PostgreSQL job queue (enqueue, claim, complete, retry, recurring ticks)
milestone: M1
kind: feature
status: review
depends_on: [T-012]
requirements: [D-13, D-74, D-75, D-76, D-77, ARCH-04, ADR-0009, MOD-05, POL-05, TST-20]
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

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Task files plus minimal crate/dependency wiring and required decision/index documentation changed; the budget overage is recorded per D-55.
- [x] Report below is filled in.

## Report

### Summary

The database queue now supports transaction-bound, idempotent enqueue; atomic PostgreSQL claims with `SKIP LOCKED`; lease recovery; completion, capped retry, and schedule materialisation. Payload guards, handler contracts, and the counts and age helpers are available to the later worker and admin tasks.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `jobs::tests::pol_backoff_doubles_and_caps` | pass |
| AC-2 | `jobs::enqueue_is_idempotent_by_unique_key` | pass |
| AC-3 | `jobs::enqueue_rolls_back_with_tx` | pass |
| AC-4 | `jobs::tst_20_concurrent_claims_are_disjoint` | pass |
| AC-5 | `jobs::lease_expiry_reclaims_and_caps_attempts` | pass |
| AC-6 | `jobs::fail_schedules_backoff_from_injected_clock` | pass |
| AC-7 | `jobs::mod_05_disabled_module_jobs_not_claimed` | pass |
| AC-8 | `jobs::payload_guard_rejects_content_like_strings` | pass |
| AC-9 | `jobs::materialise_is_idempotent_per_slot` | pass |

Additional tests cover payload byte limits, completion and lease extension, summary age/counts, and handler object safety.

### Commands run

- `cargo nextest run -p kinevo-db --features testing --test jobs` before implementation: exit 101 as expected; the new acceptance suite could not import the not-yet-implemented `kinevo_db::jobs` module.
- `cargo fmt --all`: exit 0.
- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-db --features testing --test jobs --no-fail-fast`: final run exit 0; 13 passed, 0 failed, 0 skipped. An earlier run of the same command exited 100 (11 passed, 1 failed); the lease-cap test exposed dead jobs being included in claim results, which was fixed and rerun.
- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-db --features testing`: exit 0; 49 passed, 0 failed, 0 skipped.
- `CI=true just check` without database setup: exit 101; the database tests reported `database is required in CI: PostgreSQL test database is unavailable` (16 passed, 12 failed, 125 not run). This was rerun with the disposable database helper.
- `python3 scripts/tasks_index.py`: exit 0; `0 problem(s) in 132 task(s)`.
- `eval "$(scripts/dev-postgres.sh env)" && CI=true just check` before index regeneration: exit 1 after 153 passed, 0 skipped; `tasks/index.md` was stale after the new decision references. After regenerating the index, the same command exited 0 with 153 passed, 0 skipped; `spec_tool.py` had 0 findings, contrast had 0 failures, and `tasks_index.py --check` had 0 problems. A later unthrottled repeat had one transient DB-setup failure (27 passed, 1 unavailable); `eval "$(scripts/dev-postgres.sh env)" && pg_isready -d "$KINEVO_TEST_DATABASE_URL" && psql -X -A -t "$KINEVO_TEST_DATABASE_URL" -c 'SELECT 1' >/dev/null && echo 'database connectivity ok' && CI=true cargo nextest run -p kinevo-db --features testing --test jobs -E 'test(mod_05_disabled_module_jobs_not_claimed)'` then exited 0. Final rerun `set -o pipefail; eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=4 CI=true just check 2>&1 | tail -n 35` exited 0 with all 153 tests passing.
- `python3 scripts/spec_tool.py check`: exit 0; `0 finding(s) in 60 spec files`.
- `python3 scripts/tasks_index.py --check`: exit 0; `0 problem(s) in 132 task(s)`.
- `git diff --check`: exit 0.

`just check` reported existing non-failing warnings: the `rand::thread_rng` clippy configuration entry is unreachable; `cargo deny` reported duplicate crate versions and three unused license allowances, while advisories, bans, licenses, and sources all reported `ok`.

### Files changed

Counts are additions/removals versus the pushed T-013 claim commit:

- `Cargo.lock`: +1 / −0 (generated; excluded from the budget per D-55).
- `crates/kinevo-db/Cargo.toml`: +1 / −0.
- `crates/kinevo-db/src/jobs.rs`: +477 / −0.
- `crates/kinevo-db/src/lib.rs`: +1 / −0.
- `crates/kinevo-db/tests/jobs.rs`: +565 / −0.
- `spec/decisions.md`: +4 / −0.
- `spec/index.md`: +1 / −1.
- `spec/standards/state-machines.md`: +1 / −1.
- `tasks/T-013-db-job-queue.md`: +88 / −8 (includes this report).
- `tasks/index.md`: +6 / −2.

### Deviations

- D-74 resolves the task's timestamp-only recurring key shorthand in favor of D-13's `(kind, account_id, scheduled_for)` identity by encoding account and scheduled instant in `unique_key`.
- D-75 adds caller-resolved `max_attempts` to `materialise`; the task signature omitted a required policy value, and reading policy state from `kinevo-db` or hard-coding the default was rejected.
- D-76 corrects SM-17's off-by-one retry formula so the first failed attempt uses the base delay, as the T-013 acceptance contract requires.
- D-77 defines the missing handler, worker identity, validation error, summary-age, and materialisation-result contracts. Payload limits include object keys and count Unicode characters, while the serialized-size limit counts UTF-8 bytes.
- Source and test changes exceed the approximate 400-line budget; D-55 makes that guidance non-blocking. Excluding generated `Cargo.lock`, the task diff has 1,144 additions and 12 removals (1,156 changed lines).

### Decisions made by implementer

| ID | Choice and alternatives rejected | Reason | Files touched | Reversal |
|---|---|---|---|---|
| D-74 | Encode account scope and RFC3339 slot time in `unique_key`; rejected timestamp-only keys and a schema migration. | Preserve D-13 uniqueness without changing T-012's deployed schema. | `crates/kinevo-db/src/jobs.rs`, `crates/kinevo-db/tests/jobs.rs`, this task, `spec/decisions.md`, `spec/index.md` | Supersede D-74 and update the encoding and recurring-key contract together. |
| D-75 | Pass caller-resolved `max_attempts` into `materialise`; rejected a hard-coded default or policy read in the DB crate. | Queue rows need the resolved policy value while `kinevo-db` has no policy dependency. | `crates/kinevo-db/src/jobs.rs`, `crates/kinevo-db/tests/jobs.rs`, this task, `spec/decisions.md`, `spec/index.md` | Supersede D-75 and provide another explicit policy source. |
| D-76 | Use `base × 2^(attempts−1)` and make the first retry equal the base; rejected SM-17's previous exponent. | Attempt count starts at 1 on first claim; this matches the task acceptance examples. | `crates/kinevo-db/src/jobs.rs`, `crates/kinevo-db/tests/jobs.rs`, `spec/standards/state-machines.md`, `spec/decisions.md`, `spec/index.md`, this task | Supersede D-76 and change both acceptance expectations and state-machine wording. |
| D-77 | Keep Db, IdGen, Clock, `WorkerId`, and age reference time explicit; make validation and helper return behavior stable. | Handlers need injected runtime dependencies and must open their own scoped transactions; summaries must be deterministic. | `crates/kinevo-db/Cargo.toml`, `Cargo.lock`, `crates/kinevo-db/src/jobs.rs`, `crates/kinevo-db/src/lib.rs`, `crates/kinevo-db/tests/jobs.rs`, this task, `spec/decisions.md`, `spec/index.md` | Supersede D-77 and revise the public queue/handler interfaces and tests. |

### Spec issues noticed

T-013's schedule-key shorthand and SM-17's retry exponent conflicted with the higher-level recurring identity and the acceptance examples. D-74 and D-76 record the resolutions; both are pending architect review.

### Not done / not verified

The worker dispatcher and module handlers are out of scope for T-013 and belong to T-039 and their owning tasks. The admin summary endpoint is out of scope and belongs to T-038. Hosted GitHub CI was not awaited; the local required gate passed.

### Follow-up suggestions

Review D-74 through D-77 when reviewing this task, especially the policy value supplied to recurring materialisation.

## Questions

- Q-1 — Resolved by implementer, pending architect review (D-74): T-013 shorthand sets a recurring `unique_key` to `scheduled_for`, while D-13 requires uniqueness by kind, account, and scheduled instant. Chosen: encode account scope and RFC3339 scheduled time in the key. Rejected timestamp-only keys because different accounts would collide; rejected a migration because T-012 already defines the composite key constraint.
- Q-2 — Resolved by implementer, pending architect review (D-75): `materialise` requires `max_attempts`, but the task signature omits a policy argument. Chosen: add caller-resolved `max_attempts`; rejected the hard-coded default and a DB-crate policy read.
- Q-3 — Resolved by implementer, pending architect review (D-76): SM-17 specified `base × 2^attempts`, while task acceptance expects `base × 2^(attempts−1)` with attempts starting at 1. Chosen: first failed attempt uses the base delay; state-machine text was updated.
- Q-4 — Resolved by implementer, pending architect review (D-77): the task names `JobContext`, `WorkerId`, summary age, and materialisation result without defining their shapes. Chosen contracts are documented in D-77 and covered by the added tests.
