---
id: T-253
title: Build the offline queue core: IndexedDB store, drain loop, backoff and cache persistence
milestone: M5
kind: feature
status: todo
depends_on: [T-252, T-049]
requirements: [FE-07, D-35, SEC-AC-29]
branch: m5-t253-web-offline-queue-core
budget_lines: 420
assignee: ""
---

# T-253: Build the offline queue core: IndexedDB store, drain loop, backoff and cache persistence

## Goal

User intents survive offline periods and crashes and are delivered exactly once, with no logic about business rules in the browser.

## Context

> **Queue contract** (`spec/modules/sync.md` §4): SYN-C1 an IndexedDB store keyed by `operation_id`; one intent ⇒ one id generated **once** and reused on every retry. SYN-C2 states `pending` → `in_flight` → removed on a final result, or `needs_attention` (conflict or rejected); a crash while `in_flight` returns the item to `pending` on startup. SYN-C3 drain: serial batches in FIFO order, triggered by enqueue (online), the `online` event, window focus, every 60 s while items are pending, and after login; network error/5xx/429/per-op `retry` ⇒ exponential backoff with jitter (base 2 s, cap 5 min) honouring `Retry-After`; 401 ⇒ one refresh attempt (AuthManager, T-049) then drain again, refresh failure ⇒ stop and show sign-in. SYN-C6 logout keeps the queue for the same account; logging in as another account purges it after confirmation; plain IndexedDB, a documented residual risk. SYN-C7 queue length and oldest item age are exposed.
> **Cache persistence** (FE-07): the TanStack Query cache for the allow-listed keys (`today`, `tasks`, `goals`, `schedule input`, `notes list`) persists to IndexedDB with max age 7 days and is cleared when a different account logs in. API responses are never cached by a service worker. Batch size follows `sync.batch.max_operations` from `GET /policies` (public-safe subset) with default 50.
> **Wire**: `POST /sync/reconcile` with the generated types; results are applied per op: `applied` ⇒ remove and update the cache entity; `conflict`/`rejected` ⇒ `needs_attention`; `retry` ⇒ keep and back off. Library choices from the stack map (`idb` for IndexedDB if listed; otherwise the plain API); a fake IndexedDB (`fake-indexeddb`) for tests.

## Scope

**In:**
- `web/src/offline/{queue,drain,backoff,persister,connectivity,store}.ts`, an `OfflineProvider`, hooks `useQueueStatus`, `enqueue(intent)`, unit tests with fake timers and fake IndexedDB, MSW handlers for reconcile.

**Out:**
- UI: optimistic updates, conflict card and banners: T-254.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/offline/**` | create |  |
| `web/src/app/providers.tsx` | modify | add OfflineProvider |
| `web/src/features/_shared/enqueue.ts` | create | intent helpers |
| `web/tests/offline/*.test.ts` | create | AC-1…AC-9 |

## Interfaces

```ts
export function enqueue(intent: Intent): Promise<OperationId>;       // id generated once
export function useQueueStatus(): { pending: number; needsAttention: number; oldestAgeSeconds: number | null; draining: boolean };
export function nextDelayMs(attempt: number, retryAfterMs: number | null, rand: () => number): number;
```

## Business rules

1. The operation id is created at enqueue and never regenerated.
2. Drain is serial and FIFO; one batch in flight at a time.
3. Backoff delay is `min(300 s, 2 s·2^n) ± jitter`, never less than `Retry-After`.
4. Different-account login purges the queue only after confirmation.

## Security

- The queue stores only what the user entered; no tokens.
- Logout does not leak the queue to another account.

## Implementation notes

- The `Idempotency-Key` header is not sent with sync envelopes (API-73).
- Crash recovery runs at startup before the first drain.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an enqueued intent **When** the network fails twice **Then** the same `operation_id` is sent each time | `queue::syn_c1_operation_id_reused_on_retry` |
| AC-2 | **Given** an item `in_flight` at crash **When** the app starts **Then** it is `pending` again | `queue::syn_c2_crash_recovery` |
| AC-3 | **Given** many items **When** draining **Then** serial FIFO batches of the policy size | `drain::syn_c3_fifo_serial_batches` |
| AC-4 | **Given** 429 with `Retry-After` **When** draining **Then** the next attempt waits at least that long; delays grow with jitter up to 5 minutes | `backoff::syn_c3_backoff_and_retry_after` |
| AC-5 | **Given** a 401 **When** draining **Then** one refresh then a retry; a failed refresh stops the drain and signals sign-in | `drain::syn_c3_401_refresh_once` |
| AC-6 | **Given** `applied`, `conflict`, `retry` results **When** processed **Then** removed, `needs_attention`, kept | `drain::result_handling` |
| AC-7 | **Given** logout and login as another account **When** the queue has items **Then** purge only after confirmation | `queue::syn_c6_account_switch_purge` |
| AC-8 | **Given** pending items **When** status is read **Then** count and oldest age are right | `queue::syn_c7_status` |
| AC-9 | **Given** allow-listed query data **When** persisted **Then** it is restored within 7 days and cleared for another account | `persister::fe_07_cache_persistence_and_clear` |

## Tests

```
pnpm --dir web test
pnpm --dir web check-i18n
just e2e   # environment-limited without browsers
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
