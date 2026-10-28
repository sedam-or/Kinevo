---
id: T-014
title: Add the transactional outbox (emit, dispatch with per-subscriber delivery, retry, dead letters)
milestone: M1
kind: feature
status: review
depends_on: [T-013, T-008]
requirements: [ARCH-10, ARCH-11, CA-11, ADR-0009, D-41, D-78, D-79, TST-20, ADM-FR-05]
branch: m1-t014-db-outbox
budget_lines: 400
assignee: "Codex"
---

# T-014: Add the transactional outbox (emit, dispatch with per-subscriber delivery, retry, dead letters)

## Goal

A use case writes domain events in the same transaction as its state change. A dispatcher delivers each event at least once to every registered subscriber, in id order per account, with retries, and parks repeatedly failing deliveries as `dead`.

## Context

> **Outbox (ARCH-10, `02-architecture.md` §5)**: A use case inserts event rows into `outbox_events` in the same transaction as the state change. A dispatcher job reads undelivered rows `FOR UPDATE SKIP LOCKED` in id order per account, calls each subscriber, and marks delivery per `(event_id, subscriber)` in `outbox_deliveries`. Delivery is **at-least-once**; every subscriber **must be idempotent** keyed by `event_id`. Failed deliveries retry with the backoff policy `jobs.retry.*` and then park as `dead` with an audit entry; an admin can retry them.
> **ARCH-11**: A disabled module registers no subscribers. Its events are still recorded in the outbox with no subscriber from that module.
> **CA-11**: Events are facts in the past tense with ids and enums only (no content), versioned (`kind` + `v`), idempotent to handle, defined in `kinevo-ports::events`.
> **Envelope** (`contracts.md` §6): `{ id, kind, v, account_id, occurred_at, payload }`; payloads hold ids, enums and integers only; a breaking change bumps `v`; the dispatcher routes by `(kind, v)`.
> **Schema** (`06` §3, D-78): `outbox_events(seq bigserial pk, id uuid unique, account_id, kind, payload jsonb, created_at, v smallint)` and `outbox_deliveries(event_id, subscriber, state pending|delivered|dead, attempts, next_attempt_at, last_error_code, delivered_at, pk(event_id, subscriber))`, index `outbox_deliveries_due`. The tagged payload values preserve `id`, `enum` and `int` types. `created_at` stores `EventEnvelope.occurred_at`. Grants (T-012): `kinevo_app` SELECT, INSERT on both; `kinevo_system` SELECT, INSERT on `outbox_events` and SELECT, INSERT, UPDATE on `outbox_deliveries`. No RLS.
> **Retry from the admin** (`admin.md` §2): `POST /admin/outbox/{event_id}/{subscriber}/retry` → 202 (`not_dead` 409). Because `kinevo_app` cannot UPDATE deliveries, the endpoint enqueues a job `outbox.retry` with payload `{event_id, subscriber}`; the worker performs the reset in `SystemTx` (this task provides the function; T-038 provides the endpoint).
> **Dispatch order**: events of one account are delivered to a subscriber in `seq` order; a failing delivery blocks later events of the *same account and subscriber* until it succeeds or goes dead (so state changes are never applied out of order); other accounts and subscribers continue.

## Scope

**In:**
- `kinevo_db::outbox`:
  - `emit(conn: &mut impl TxConn, ids: &dyn IdGen, now: Instant, env: EventEnvelope, subscribers: &SubscriberRegistry) -> Result<EventId, AppError>`: inserts the event, and one `pending` delivery row for every subscriber registered for `(kind, v)` (decided at emit time, `next_attempt_at = now`). Events with no subscriber are stored without deliveries (ARCH-11).
  - `SubscriberRegistry` (built at startup by `kinevo-app` from module descriptors of **enabled** modules): `register(subscriber: SubscriberName, kind: &'static str, v: u16)`, `for_event(kind, v) -> &[SubscriberName]`. Subscriber names are `"<module>.<handler>"`.
  - `Subscriber` trait (`async-trait`, object-safe): `fn name(&self) -> SubscriberName; async fn handle(&self, env: &EventEnvelope) -> Result<(), AppError>` (idempotent by `env.id`). Add `async-trait` to `kinevo-db`'s dependencies.
  - `dispatch_once(db: &Db, subs: &SubscriberSet, now: Instant, retry: RetryPolicy, max_attempts: u32, batch: u16) -> Result<DispatchStats, AppError>`: in a `SystemTx` selects due `pending` deliveries (join to their events), grouped per `(account, subscriber)` in `seq` order with `FOR UPDATE OF d SKIP LOCKED`, keeping only the first due delivery per `(account, subscriber)`; commits the claim (marks nothing), runs the subscriber **outside** the claiming transaction (no lock held while handlers run), then in a second `SystemTx` marks `delivered` or increments `attempts` and sets `next_attempt_at = now + backoff` (T-013's `backoff_seconds`), or `dead` at `max_attempts`. A crash between handler success and marking causes a redelivery, which is the at-least-once contract.
  - `retry_dead(tx: &mut SystemTx, event_id, subscriber, now) -> Result<(), AppError>` (`not_found`, `not_dead` 409).
  - `counts(conn) -> OutboxCounts { pending, dead }` for the admin summary and metrics.
- The event envelope lives in `kinevo-kernel::event` (`EventEnvelope`, `EventPayload`, kind constants), because `kinevo-db` may depend only on `kinevo-kernel` (CA-03). `kinevo-ports::events` re-exports it with `pub use kinevo_kernel::event::*;` (T-008 creates that re-export; this task adds the kernel module).

**Out:**
- The recurring "dispatcher loop" scheduling inside the worker: T-039.
- The `outbox.retry` job handler and the HTTP retry endpoint: T-038.
- Subscribers themselves (later milestones).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-kernel/src/event.rs` | modify | stable event-kind storage names and typed payload round trip |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod event;` |
| `crates/kinevo-ports/src/events.rs` | modify | becomes `pub use kinevo_kernel::event::*;` |
| `crates/kinevo-db/src/outbox.rs` | create | everything above |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod outbox;` |
| `crates/kinevo-db/tests/outbox.rs` | create | component and concurrency tests |
| `crates/kinevo-db/migrations/20261101000400_platform_outbox_event_version.sql` | create | persist the version required for `(kind, v)` routing; keep the `platform_` migration-module prefix |
| `crates/kinevo-db/tests/platform_tables.rs` | modify | assert the additive outbox version column |
| `spec/06-domain-model.md`, `spec/decisions.md`, `spec/index.md` | modify | document the persisted event version and payload representation |

## Interfaces

```rust
pub struct SubscriberName(&'static str);
#[async_trait] pub trait Subscriber: Send + Sync { fn name(&self) -> SubscriberName; async fn handle(&self, e: &EventEnvelope) -> Result<(), AppError>; }
pub struct SubscriberRegistry;  impl SubscriberRegistry { pub fn register(&mut self, s: SubscriberName, kind: &'static str, v: u16); pub fn for_event(&self, kind: &str, v: u16) -> &[SubscriberName]; }
pub async fn emit(conn: &mut impl TxConn, ids: &dyn IdGen, now: Instant, env: EventEnvelope, reg: &SubscriberRegistry) -> Result<EventId, AppError>;
pub struct DispatchStats { pub delivered: u32, pub retried: u32, pub dead: u32 }
pub async fn dispatch_once(db: &Db, subs: &SubscriberSet, now: Instant, retry: RetryPolicy, max_attempts: u32, batch: u16) -> Result<DispatchStats, AppError>;
```

## Business rules

1. `emit` is part of the caller's transaction: a rollback removes the event and its deliveries.
2. A delivery is attempted only when `next_attempt_at <= now` and no earlier undelivered, non-dead delivery exists for the same `(account, subscriber)`.
3. After `max_attempts` failures the delivery is `dead`; later events for that `(account, subscriber)` then proceed (a dead delivery does not block forever) and an audit entry `outbox.retried` is **not** written here (that is for the admin retry; the dead transition writes `audit_log` action `outbox.dead` through the caller-supplied `AuditWriter` closure, see Implementation notes).
4. `retry_dead` resets `state = pending`, `attempts = 0`, `next_attempt_at = now`.
5. Handlers run outside any database lock; a slow handler delays only its own `(account, subscriber)` stream.

## Security

- Payloads contain ids, enums and integers only (kernel `EventPayload` has no string-valued variant except enum literals).
- `last_error_code` stores a registry code, never an error message.
- Subscribers open their own `Tx::begin(account_id)`; the dispatcher passes `account_id` through the envelope and has no tenant access (ADR-0011).

## Implementation notes

- Add an optional hook `on_dead: &dyn Fn(&mut SystemTx, EventId, SubscriberName) -> BoxFuture<…>` so `kinevo-app` can write the audit entry (`AuditAction::OutboxDead`, `"outbox.dead"`, is defined by T-012).
- A test subscriber records `event.id` into a vector so duplicates are visible.
- Selecting "first due delivery per (account, subscriber)" can use `DISTINCT ON (e.account_id, d.subscriber) … ORDER BY e.account_id, d.subscriber, e.seq` over undelivered rows, then filter by `next_attempt_at <= now`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a `Tx` that emits an event and rolls back **When** the tables are read **Then** neither event nor deliveries exist | `outbox::arch_10_emit_is_transactional` |
| AC-2 | **Given** two registered subscribers for `(TaskCompleted, 1)` **When** an event is emitted **Then** one event row and two `pending` deliveries exist; an event kind with no subscriber has zero deliveries | `outbox::arch_11_events_without_subscribers_are_kept` |
| AC-3 | **Given** three events for one account **When** `dispatch_once` runs repeatedly **Then** the subscriber sees them in `seq` order, each exactly once on success | `outbox::dispatch_preserves_per_account_order` |
| AC-4 | **Given** a subscriber failing twice then succeeding **When** dispatch runs at advancing injected times **Then** `attempts` grows with the backoff of T-013, later events of that account wait, then are delivered | `outbox::failing_delivery_blocks_only_its_stream_and_retries` |
| AC-5 | **Given** `max_attempts = 3` and a permanent failure **When** dispatch runs 3 times **Then** the delivery is `dead`, `on_dead` was called once, and later events flow | `outbox::arch_10_dead_after_max_attempts` |
| AC-6 | **Given** a dead delivery **When** `retry_dead` runs **Then** it is `pending` with `attempts = 0`; on a non-dead delivery `not_dead` 409; unknown pair `not_found` | `outbox::retry_dead_rules` |
| AC-7 | **Given** a subscriber that succeeds but the process "crashes" before marking (simulated by dropping the second transaction) **When** dispatch runs again **Then** the subscriber receives the same `event.id` again | `outbox::at_least_once_redelivery_after_crash` |
| AC-8 | **Given** two dispatchers running concurrently over 30 events **When** both finish **Then** every `(event, subscriber)` is delivered at least once and per-stream order holds | `outbox::tst_20_concurrent_dispatchers` |

## Tests

```
cargo nextest run -p kinevo-kernel -p kinevo-ports -p kinevo-db --features testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally.
- [x] Files changed and deviations are recorded; the implementation exceeds the approximate budget, which is non-blocking under D-55.
- [x] Report below is filled in.

## Report

### Summary

The transactional outbox now writes an event and its registered pending deliveries in the caller's transaction, persists event version and typed payload values, and dispatches one due event at a time per account/subscriber stream. Handlers run outside the claim transaction; results are committed separately with injected time, retry backoff, dead-lettering, optional audit recording, retry reset, and count helpers. PostgreSQL acceptance tests cover transaction rollback, subscriber routing, ordering, retry isolation, dead letters, redelivery, and concurrent dispatchers.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `outbox::arch_10_emit_is_transactional` | pass |
| AC-2 | `outbox::arch_11_events_without_subscribers_are_kept` | pass |
| AC-3 | `outbox::dispatch_preserves_per_account_order` | pass |
| AC-4 | `outbox::failing_delivery_blocks_only_its_stream_and_retries` | pass |
| AC-5 | `outbox::arch_10_dead_after_max_attempts` | pass |
| AC-6 | `outbox::retry_dead_rules` | pass |
| AC-7 | `outbox::at_least_once_redelivery_after_crash` | pass |
| AC-8 | `outbox::tst_20_concurrent_dispatchers` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `cargo nextest run -p kinevo-db --features testing --test outbox` (before implementation) | 101 | Expected red: `could not find outbox in kinevo_db`. |
| `cargo nextest run -p kinevo-db --features testing --test outbox` | 0 | 8/8 acceptance tests passed. |
| `NEXTEST_TEST_THREADS=4 cargo nextest run -p kinevo-kernel -p kinevo-ports -p kinevo-db --features testing` | 0 | 115/115 tests passed, 0 skipped. |
| `NEXTEST_TEST_THREADS=4 just check` | 0 | Format and Clippy passed; workspace 163/163 tests passed; `cargo deny check` completed with existing duplicate-crate and unused-license-allowance warnings; spec check 0 findings, contrast 0 failures, task index 0 problems. |
| `cargo fmt --all` | 0 | Applied rustfmt output. The initial `cargo fmt --all -- --check` exited 1 on formatting differences; final format check is included in the passing `just check`. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated the index after task status/requirements changes. |
| `python3 scripts/spec_tool.py check` | 0 | 0 findings in 60 spec files. |
| `python3 scripts/contrast.py` | 0 | 0 failures. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |
| `git diff --check` | 0 | Clean. |

An earlier `just check` exited 101 because Clippy found `needless_pass_by_value` in `EventPayload::from_storage_json`; the API now borrows the JSON value and the final `just check` passes. No required check was skipped and no environment-limited condition occurred.

### Files changed

Counts are task-delta additions/removals against the T-014 claim commit `c175b86d932089a6caf7847b888cf763d2aae1b3`.

| Path | Added | Removed |
|---|---:|---:|
| `crates/kinevo-db/migrations/20261101000400_platform_outbox_event_version.sql` | 7 | 0 |
| `crates/kinevo-db/src/lib.rs` | 1 | 0 |
| `crates/kinevo-db/src/outbox.rs` | 458 | 0 |
| `crates/kinevo-db/tests/outbox.rs` | 669 | 0 |
| `crates/kinevo-db/tests/platform_tables.rs` | 1 | 0 |
| `crates/kinevo-kernel/src/event.rs` | 157 | 12 |
| `spec/06-domain-model.md` | 3 | 2 |
| `spec/decisions.md` | 2 | 0 |
| `spec/index.md` | 1 | 1 |
| `tasks/T-014-db-outbox.md` | 96 | 10 |
| `tasks/index.md` (generated) | 4 | 2 |

Non-generated files total 1,395 additions and 25 removals (1,420 changed lines); generated `tasks/index.md` adds 4 and removes 2 and is excluded from the budget.

### Deviations

- D-78 resolves the missing `v` column and payload round-trip gaps: an additive platform migration stores `v`, payload values are tagged as `id`, `enum`, or `int`, enum literals are validated as code-like tokens, and `created_at` stores the envelope's `occurred_at`. The caller-supplied `EventEnvelope.id` remains canonical; the task's redundant `IdGen` parameter is retained for signature compatibility.
- D-79 resolves AC-2's `TaskCompleted` example against T-008's closed M1 event set: the routing test uses the existing `AccountRegistered` kind. It still verifies routing by `(kind, v)` to two subscribers.
- Added the version migration and the platform schema assertion, and updated the domain schema, decision log, spec index, task report, and generated task index. `event.rs` already existed from T-008, so it was extended rather than recreated; `kinevo-kernel/src/lib.rs` and `kinevo-ports/src/events.rs` already contained the required module and re-export and did not need changes. `async-trait` was already present from T-013, so no dependency or lockfile change was needed.
- The approximate `budget_lines: 400` guidance is exceeded by the implementation and acceptance tests; D-55 makes this a recorded deviation rather than a blocker.
- Follow-up in T-022 (D-88): the migration filename was corrected to include the required `platform_` module prefix after the combined migration validator rejected the original task path. Its SQL body and migration version are unchanged.
- Follow-up in T-022 (D-90): the migration integration test now derives its expected applied count from the declared migration sources rather than a fixed count that became stale as migrations were added.
- Follow-up in T-022 (D-91): the dispatcher now formats `created_at` as UTC RFC3339 at the SQL boundary, matching the kernel instant parser.
- Follow-up in T-022 (D-92): the retry integration test now expects independent progress per `(account, subscriber)` stream, as required by the task's ordering rules.
- Follow-up in T-022 (D-93): the retention test fixture now supplies the required `outbox_events.v` value introduced by D-78.
- Follow-up in T-022 (D-94): the platform schema acceptance test compares column names, types and nullability independent of physical order, as additive migrations append columns.
- Follow-up in T-022 (D-95): the schema constraint inventory now includes the positive `outbox_events.v` check from D-78.
- Dead-letter auditing is exposed as an optional `DeadLetterHook` on `SubscriberSet`; `AuditDeadLetterHook` records `outbox.dead` atomically with the dead transition. Runtime wiring belongs to the worker/composition-root task.

### Decisions made by implementer

| ID | Choice and alternatives rejected | Reason | Files touched | Reversal |
|---|---|---|---|---|
| D-78 | Add `outbox_events.v`; persist explicit `id`/`enum`/`int` tags; validate enum literals; use `created_at = occurred_at`; keep `env.id`. Rejected encoding the version into `kind`, guessing ID versus enum from untagged strings, dropping `v`, using dispatch time as event occurrence, or replacing `env.id`. | The task routes by `(kind, v)` and subscribers require the same typed event and id for idempotency, but the existing schema had no version or occurrence column and the payload JSON was untagged. | `crates/kinevo-db/migrations/20261101000400_platform_outbox_event_version.sql`, `crates/kinevo-db/src/outbox.rs`, `crates/kinevo-kernel/src/event.rs`, `crates/kinevo-db/tests/platform_tables.rs`, `crates/kinevo-db/tests/outbox.rs`, `spec/06-domain-model.md`, `spec/decisions.md`, `spec/index.md`, `tasks/T-014-db-outbox.md` | Supersede D-78 and update the migration/schema, payload format, emission contract, and round-trip tests together. |
| D-79 | Use `AccountRegistered` in AC-2 instead of adding `TaskCompleted` to M1 `EventKind`. | T-008 defines the closed M1 set; an M2 event kind should not be introduced early to exercise generic routing. | `crates/kinevo-db/tests/outbox.rs`, `spec/decisions.md`, `spec/index.md`, `tasks/T-014-db-outbox.md` | Supersede D-79 and update the event registry plus acceptance coverage when the later event kind is introduced. |

### Spec issues noticed

- The T-014 inline schema excerpt omitted `v`, while the envelope and acceptance contract require dispatch by `(kind, v)`. Resolved by D-78 and the additive migration; pending architect review.
- AC-2 names a later `TaskCompleted` event although T-008 closes the M1 event set. Resolved for this task by D-79; pending architect review.

### Not done / not verified

- Environment-limited items: none.
- `kinevo-app` runtime assembly and the recurring dispatcher worker loop are not part of the listed T-014 files; T-039 wires the worker. The `outbox.retry` job handler and HTTP endpoint remain T-038.
- The hosted runtime path was not exercised; the database tests used isolated PostgreSQL test databases with the real application/system roles.

### Follow-up suggestions

Wire `SubscriberRegistry`, `SubscriberSet`, and `AuditDeadLetterHook` in the worker/composition root when implementing T-039.

## Questions

- Resolved by implementer, pending architect review (D-78): the platform table omitted `v`, although this task routes by `(kind, v)`; the added platform migration stores the version, and tagged payload values preserve ID/enum/integer types across database round trips. The existing `EventEnvelope.id` remains the canonical event id; regenerating it inside `emit` would break subscriber idempotency. See Report for alternatives and reversals.
- Resolved by implementer, pending architect review (D-79): AC-2's `TaskCompleted` example is not in T-008's closed M1 `EventKind` set, so its named routing test uses the existing `AccountRegistered` kind. See Report for alternatives and reversals.
