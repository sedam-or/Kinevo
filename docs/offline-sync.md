# Kinevo — Offline & Synchronization

**Authority:** ADR-017 (Offline Mutation Reconciliation and Operation Ledger).
This document describes the implemented contract; it does not overpromise.

### Principle
Offline mode increases continuity, but PostgreSQL remains authoritative.
IndexedDB is a cache/queue, never the canonical source of truth.

### Offline scope (implemented — ADR-017 §2.1)
The server-side operation-type allowlist decides what may reconcile offline.
OFFLINE_SUPPORTED (v1): `task:create`, `task:update`, `task:status`,
`subtask:create`, `note:create`, `note:update`.

Everything else is ONLINE_ONLY and fails visibly offline (no silent queueing):
billing, auth/profile, task lock/unlock, ALL schedule mutations (draft apply,
reschedule apply, Sync Now, mini/emergency pause, auto-swap, overrides), quick
capture (its placement writes the canonical schedule — create a task offline
and use Sync Now online), canvas rich-content saves, subtask toggle, imports,
attachments/uploads, AI. Full matrix:
`docs/convergence/OFFLINE_CAPABILITY_MATRIX_2026-08-31.md`.

### Local storage
IndexedDB stores (all version 1):
- `kinevo-mutation-queue` — the durable MutationQueue (operation envelopes
  with status, persisted BEFORE local success — survives tab close/reload);
- `kinevo-today-cache` — Today read-cache store (module shipped; the running
  Today view still loads live — a bounded rehydration after reconciliation is
  used instead of an offline read cache);
- `kinevo-offline` — canvas queue + local snapshots (canvas path remains
  online-only for mutations; the in-memory pending scene is not durable).

### Operation envelope (protocol v1 — ADR-017 §2.2)
```json
{
  "protocol_version": 1,
  "operation_id": "uuid",
  "operation_type": "task:update",
  "entity_type": "task",
  "entity_id": 42,
  "client_reference_id": null,
  "payload": { "title": "…", "base_version": 4 },
  "base_version": 4,
  "workspace_id": 3,
  "client_created_at": "2026-08-31T…Z"
}
```
`client_created_at` is observability metadata ONLY — it never decides which
write wins.

### Server operation ledger (`offline_operations`)
One row per `(user_id, operation_id)` records the at-most-once outcome:
`status` ∈ {applied, conflict, rejected}, a SHA-256 `payload_hash` of the
canonicalized payload, and a BOUNDED result (`entity_id`, `version`, code) —
never full note/task content. Retention: `offline.ledger_retention_days`
(default 90); `offline:prune-ledger` runs daily; an expired replay is rejected
(`EXPIRED`) and the client regenerates its operation_id.

### Idempotency (ADR-017 §2.3, §2.5)
- FIRST request → execute at most once, record outcome;
- SAME id + identical payload → replay recorded outcome (no re-execution) —
  the response-loss safety net;
- SAME id + different payload → rejected deterministically (`REUSED`).

### Reconcile endpoint — `POST /sync/reconcile`
Distinct from schedule Sync Now (`POST /schedule/sync`). Batch ≤ 50 operations;
per-op payload ≤ 64 KB; request ≤ 512 KB; `throttle:reconcile`. One outcome
per operation (transaction-per-operation, sequential FIFO). A bad operation
never corrupts its neighbours. Response: `outcomes[]` (applied | conflict |
rejected, with `replay` flag and canonical/bounded result) + `needs_review[]`.

### Conflict semantics (ADR-017 §2.9)
Existing optimistic versions are reused — no last-write-wins, no CRDT.
- `task:update` — `base_version` guard (new; task content mutations now bump
  the task version) → VERSION_CONFLICT, server state never overwritten;
- `note:update` — existing `base_version` → VERSION_CONFLICT;
- `task:status` — semantic idempotency (already-in-target-status is applied);
- creates — idempotent by ledger.
A conflict is recorded in the ledger and returned in `needs_review`; the UI
surfaces "Some changes need review" with a "Discard local change" action
(`queue.discardConflicts()`).

### Online convergence (ADR-017 §2.11)
The online allowlist mutations accept an optional `X-Operation-Id` header and
flow through the SAME ledger path (`reconcileOne`), so a response-loss retry of
an online request never double-applies. The offline and online paths converge
on the same application use cases.

### Queue semantics (web — ADR-017 §2.14/§2.15)
- append + persist before local success (IndexedDB);
- drain FIFO on reconnect AND on authenticated boot with queued items;
- `offline/reconcile-applier.ts` posts each envelope to `/sync/reconcile` and
  maps outcomes (applied → applied; conflict → failed_permanent + marker;
  rejected/expired → failed_permanent + error; network/429/5xx → retryable);
- conflict/rejected are surfaced and never endless-retried; `failed_retryable`
  retries safely;
- a network failure of an ONLINE allowlist request enqueues the SAME
  operation_id (safe replay);
- after a drain, the touched stores are rehydrated from canonical responses
  (bounded refresh, no full-page reload);
- offline reload keeps the session (auth restore no longer clears the token on
  network failure).

### Sync state machine / visible states (TASK-115)
```text
Idle → Offline → Queued → Syncing → Applied → FailedRetryable → FailedPermanent
```
Visible: **Online**, **Offline**, **Queued** ("Waiting to sync"), **Syncing**,
**Saved** (only after server acknowledgement), **Conflict** ("Some changes need
review"), **Retrying**, **Failed** ("Could not sync this change"). "Saved" is
never claimed before server acknowledgement. States are text + badge (never
color-only), keyboard reachable, and presented as one aggregate status (no per
operation toast spam).

### Service Worker
Responsibilities: cache the app shell, enable offline navigation where in
scope, never intercept business API traffic, never become a second
business-logic engine. Business API calls pass through untouched.

### Sync flow
```text
Connection lost → local mutation persisted (durable queue)
 → UI marks queued → connection restored (or boot)
 → /sync/reconcile → server validates ownership/version/idempotency
 → apply or conflict → client applies canonical outcome / clears the queue
```

### Failure safety
A failed sync MUST NOT silently discard local data. Permanent conflict requires
user-visible reconciliation (review + discard). A rejected operation preserves
the local intent only until the user reviews it.

### Mobile (NativePHP) — truthful status (ADR-017 §2.19)
Mobile screens show `offline`/`queued` STATUS LABELS ONLY. There is no durable
offline persistence on mobile: a queued CaptureScreen draft is a
component-property value lost on navigation; all mobile mutations are live HTTP.
Mobile durable offline persistence is deferred to the Android
production-hardening phase, but the SERVER protocol (envelope + ledger +
`/sync/reconcile`) is the mobile contract and is reusable unchanged.
`mobile-architecture.md` reflects this honestly.