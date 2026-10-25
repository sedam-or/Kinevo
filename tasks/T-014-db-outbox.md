---
id: T-014
title: Add the transactional outbox (emit, dispatch with per-subscriber delivery, retry, dead letters)
milestone: M1
kind: feature
status: todo
depends_on: [T-013, T-008]
requirements: [ARCH-10, ARCH-11, CA-11, ADR-0009, D-41, TST-20, ADM-FR-05]
branch: m1-t014-db-outbox
budget_lines: 400
assignee: ""
---

# T-014: Add the transactional outbox (emit, dispatch with per-subscriber delivery, retry, dead letters)

## Goal

A use case writes domain events in the same transaction as its state change. A dispatcher delivers each event at least once to every registered subscriber, in id order per account, with retries, and parks repeatedly failing deliveries as `dead`.

## Context

> **Outbox (ARCH-10, `02-architecture.md` §5)**: A use case inserts event rows into `outbox_events` in the same transaction as the state change. A dispatcher job reads undelivered rows `FOR UPDATE SKIP LOCKED` in id order per account, calls each subscriber, and marks delivery per `(event_id, subscriber)` in `outbox_deliveries`. Delivery is **at-least-once**; every subscriber **must be idempotent** keyed by `event_id`. Failed deliveries retry with the backoff policy `jobs.retry.*` and then park as `dead` with an audit entry; an admin can retry them.
> **ARCH-11**: A disabled module registers no subscribers. Its events are still recorded in the outbox with no subscriber from that module.
> **CA-11**: Events are facts in the past tense with ids and enums only (no content), versioned (`kind` + `v`), idempotent to handle, defined in `kinevo-ports::events`.
> **Envelope** (`contracts.md` §6): `{ id, kind, v, account_id, occurred_at, payload }`; payloads hold ids, enums and integers only; a breaking change bumps `v`; the dispatcher routes by `(kind, v)`.
> **Schema** (`06` §3): `outbox_events(seq bigserial pk, id uuid unique, account_id, kind, payload jsonb, created_at)` and `outbox_deliveries(event_id, subscriber, state pending|delivered|dead, attempts, next_attempt_at, last_error_code, delivered_at, pk(event_id, subscriber))`, index `outbox_deliveries_due`. Grants (T-012): `kinevo_app` SELECT, INSERT on both; `kinevo_system` SELECT, INSERT on `outbox_events` and SELECT, INSERT, UPDATE on `outbox_deliveries`. No RLS.
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
| `crates/kinevo-kernel/src/event.rs` | create | `EventEnvelope`, `EventPayload`, kind constants |
| `crates/kinevo-kernel/src/lib.rs` | modify | `pub mod event;` |
| `crates/kinevo-ports/src/events.rs` | modify | becomes `pub use kinevo_kernel::event::*;` |
| `crates/kinevo-db/src/outbox.rs` | create | everything above |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod outbox;` |
| `crates/kinevo-db/tests/outbox.rs` | create | component and concurrency tests |

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

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
