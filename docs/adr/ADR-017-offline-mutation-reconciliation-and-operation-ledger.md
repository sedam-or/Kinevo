# ADR-017 — Offline Mutation Reconciliation and Operation Ledger

- **Status:** ACCEPTED (2026-08-31)
- **Supersedes:** none (replaces the aspirational claims in `docs/offline-sync.md`
  that were never wired to production — see §1)
- **Amends:** none
- **Resolves:** BLOCKER-OFFLINE-01 (client offline concepts exist but no
  authoritative server reconciliation contract)
- **Companion:** `docs/roadmap/archive/convergence/PRE_CONVERGENCE_BASELINE.md`,
  `docs/offline-sync.md` (rewritten to match reality)

## 1. Context — current offline reality

The audit (2026-08-31) established:

- A complete web `MutationQueue` module (`resources/js/offline/*`), an
  IndexedDB store (`kinevo-mutation-queue`), an LWW policy, a Today read cache,
  a `SyncStatusController`, and a service worker exist and are unit-tested —
  but are **dead code**: no Pinia store or component ever calls
  `queue.enqueue()`. Only the shell badge is wired (always shows online/offline
  because the queue is never fed).
- The service worker caches the app shell only; API requests pass through.
- Today is NOT cached for offline reads in the running app.
- Offline reload logs the user out (`auth/store.ts` clears the token on any
  `restoreSession` failure, including the `OFFLINE` network error).
- `docs/offline-sync.md` claims persistence-through-tab-close, cached Today,
  and server idempotency that **do not exist**.
- Server-side idempotency exists only for server-generated deterministic
  operation ids (activity_logs, progress_events, ai, billing). No endpoint
  consumes a **client-supplied** operation_id. Mobile `CaptureScreen` sends an
  `operation_id` the server drops.
- Mobile (`NativeComponents`) has only status labels (`queued`, `offline`) —
  no durable offline persistence.

## 2. Decision

### 2.1 Supported mutation model — bounded allowlist, single authority

A server-side operation-type allowlist decides what may reconcile offline.
Dispatch is a closed `switch` over the allowlist — never a generic RPC proxy,
never arbitrary URL replay, never client-controlled class/method strings.

**OFFLINE_SUPPORTED (v1):**

| operation_type | entity | versioned | offline idempotency |
|---|---|---|---|
| `task:create` | task | — | ledger (operation_id) |
| `task:update` | task | `base_version` (NEW optional optimistic check, §2.11) | ledger + version conflict |
| `task:status` | task | semantic (state machine) | already-in-target-status → applied |
| `subtask:create` | subtask | — | ledger |
| `note:create` | note | — | ledger |
| `note:update` | note | `base_version` (existing) | ledger + version conflict |

**ONLINE_ONLY (explicit, not in the allowlist):** billing and payment;
authentication, profile and security mutations; task lock/unlock; all schedule
mutations (draft apply, reschedule apply, `schedule/sync` Sync Now, mini-pause,
emergency-pause, auto-swap, schedule overrides); **quick capture** (its
placement writes the canonical schedule — offline replay could silently collide
with newer reality); canvas rich-content saves and binary assets; subtask
toggle (flip semantics are not idempotent and the queue carries no desired-state
contract); goal/milestone/program mutations (deferred — a future allowlist
extension); imports confirm/discard; AI operations; attachment/uploads.

Rationale: quick capture and schedule mutations mutate the canonical schedule
(authoritative, ADR-015/016) and are therefore excluded from offline replay —
users create a task offline and use Sync Now when online (ADR-016).

### 2.2 Operation envelope (protocol v1)

```jsonc
{
  "protocol_version": 1,
  "operation_id": "client-uuid",        // idempotency identity, NOT authorization
  "operation_type": "task:create",
  "entity_type": "task",
  "entity_id": 42 | null,               // server id, or null for creates
  "client_reference_id": "temp-1",      // client temp id for creates (correlation only)
  "payload": { /* the exact canonical request body */ },
  "base_version": 4 | null,             // where applicable
  "workspace_id": 3 | null,             // snapshotted at enqueue (see §2.13)
  "client_created_at": "2026-08-31T...Z" // observability metadata ONLY
}
```

`client_created_at` is display/observability metadata. It **never** decides
which write wins.

### 2.3 Operation identity

- Uniqueness scope: **`(user_id, operation_id)`** — a user/client replay is
  globally unique per user; cross-user collisions are impossible by scoping.
- `payload_hash` = SHA-256 of the **canonicalized** payload JSON
  (keys recursively sorted, `JSON_UNESCAPED_SLASHES`).

| case | behavior |
|---|---|
| first request | execute **at most once**, record outcome |
| same id + **identical** payload (same hash) | return the previously recorded canonical outcome — **no re-execution** |
| same id + **different** payload (different hash) | **reject deterministically** (422, `operation_id reused with a different payload`) |

### 2.4 Server operation ledger

`offline_operations` (PostgreSQL):

| column | purpose |
|---|---|
| `id`, `user_id` | PK, owner scope |
| `operation_id` (64) | client identity; **unique `(user_id, operation_id)`** is the at-most-once guard |
| `operation_type`, `entity_type` | allowlist + index |
| `entity_id` | canonical id (null for creates until applied) |
| `payload_hash` (sha256) | semantic identity |
| `status` (`applied` \| `conflict` \| `rejected`) | minimal terminal state — **no workflow engine** |
| `result` (json) | **bounded**: `{entity_id, version, code}` — never full note/task content |
| `created_at`, `processed_at` | retention + audit |

Transient server failures leave **no** ledger row (the transaction rolls back),
so the client retry safely re-executes. A replay whose row exists returns the
recorded outcome — this is the **response-loss safety net**
(§2.10 critical case).

### 2.5 Ledger retention

- `config('offline.ledger_retention_days')` default **90 days** (generous for
  realistic offline periods; applied rows are deleted client-side immediately
  after reconciliation, so the ledger only persists for safety replays).
- `offline:prune-ledger` command deletes rows older than the horizon; scheduled
  daily (next to the existing scheduler entries).
- Replay of an operation older than retention → **`rejected` with code
  `expired`** — the client regenerates a new operation_id. Entity-level
  correctness after expiry is still guaranteed by the entity's own
  version/409 contract, not the ledger.

### 2.6 Reconciliation application service — single convergence point

`OfflineReconciliationService` (application layer) is the **only** dispatcher:

- `reconcileOne(int $userId, Operation $operation, callable $invoke)` —
  shared by online controllers and the batch endpoint.
- `reconcileBatch(int $userId, array $operations): array<OperationOutcome>` —
  the `/sync/reconcile` path.
- Dispatch is a closed `switch (operation_type)` that validates the payload
  with the **same validator rules** the online endpoints use and invokes the
  **same application use cases** (`CreateTaskUseCase`, `UpdateTaskUseCase`,
  `SetTaskStatusUseCase`, `AddSubtaskUseCase`, `CreateNoteUseCase`,
  `UpdateNoteUseCase`).
- **No business logic is copied** into the reconciliation service — the offline
  and online paths converge on the same use-case authority.
- Each operation executes inside its **own `DB::transaction`**, with the ledger
  row written in the same transaction (atomic: either the mutation AND its
  ledger record commit, or neither).

### 2.7 API — `POST /sync/reconcile`

Clearly distinct from schedule Sync Now (`POST /schedule/sync`):

```
POST /api/v1/sync/reconcile
{ "operations": [ <envelope>, ... ] }          // ≤ 50
→
{
  "outcomes": [
    { "operation_id", "status": "applied|conflict|rejected|expired",
      "entity_type", "entity_id", "result_version",
      "result": { ... canonical entity shape, first-apply only ... },
      "code": "CONFLICT|REJECTED|EXPIRED|..." }
  ],
  "needs_review": [ "operation_id", ... ]       // conflicts the client must surface
}
```

- Bounded: batch ≤ 50; per-operation payload ≤ 64 KB; total request ≤ 512 KB.
- One outcome per operation; a bad operation never corrupts the batch (§2.8).
- A first-apply returns the **same canonical entity shape** the online endpoint
  returns (`result.task`, `result.note`, `result.subtask`) so client stores
  apply it unchanged. A ledger **replay** returns only the recorded bounded
  result (`entity_id`, `version`) — the client refetches the entity.

### 2.8 Batch semantics

- Sequential, FIFO, **transaction-per-operation** (recommended default). No
  two operations in the allowlist form one atomic domain command, so no
  cross-operation transaction is required.
- A conflict/rejection in operation N does **not** roll back operations 1..N−1
  and does **not** block N+1..; each returns its own outcome.
- The queue preserves enqueue order; the server processes the batch in the
  order received.

### 2.9 Conflict semantics

Reuse existing optimistic versioning / 409 semantics. **No generic
last-write-wins, no merge-anything, no CRDT.**

| operation | conflict rule |
|---|---|
| `task:update` | `base_version` vs current task `version` mismatch → **conflict**; server state not overwritten; canonical version returned |
| `note:update` | existing `base_version` → **conflict** (unchanged behavior) |
| `task:status` | **semantic idempotency**: entity already in target status → `applied` (no re-transition); invalid transition for current state → `conflict` |
| creates | no version; idempotency by ledger |

A conflict outcome returns the canonical state (`result_version`, `entity_id`)
and flags the operation in `needs_review`. The client **must not** silently
overwrite server state; it surfaces review (§2.18).

### 2.10 Failure / recovery semantics

Critical case:

> SERVER COMMITS → NETWORK RESPONSE LOST → CLIENT RETRIES the SAME
> `operation_id` → the ledger returns the recorded outcome — the mutation is
> **never executed twice**.

Other cases:

- **duplicate operation (same id + identical payload)** → replay, recorded outcome.
- **same id + different payload** → 422 reject.
- **stale entity** → conflict (§2.9).
- **deleted entity** → the use case's `Task not found` / `Note not found` →
  `rejected` (code `not_found`); client surfaces and drops the queued item.
- **archived/renamed Workspace** → the use case's ownership/workspace validation
  rejects → `rejected` (`workspace`).
- **server validation changed while offline** → payload re-validated on replay →
  `rejected` with the validation errors; client surfaces.
- **entitlement changed** → the use case's entitlement check (where present)
  re-runs → `rejected` (`entitlement`).
- **auth expired during drain** → 401; the client does NOT drop queued intent
  (§2.16).
- **account changed on a shared device** → the queue is keyed per
  authenticated user; a different user's reconcile is owner-scoped and the
  previous user's queue is never replayed (§2.16).
- **ledger row exists but canonical entity later changed** → replay returns the
  recorded outcome (not a re-application). The client refetches canonical state.
- **database rollback** → the ledger row and mutation commit atomically
  (per-operation transaction) — no half state.
- **partial batch** → per-op outcomes; already-applied ops stay applied.

### 2.11 Online-path convergence (idempotency for allowlist mutations)

The online controllers for allowlist mutations accept an optional
`X-Operation-Id` header. When present, the controller routes the mutation
through the same `reconcileOne` ledger path:

- online success → ledger row recorded in the same transaction;
- online network loss → the store enqueues the same operation_id → replay
  returns the recorded outcome (no double-create);
- absent header → unchanged legacy path (no ledger write) for existing clients.

This closes the pre-existing double-create risk on retried online requests
**for the allowlist set only** and guarantees the offline/online paths converge
on the same use-case authority.

### 2.12 Create operations / temporary ids

- Client generates a `client_reference_id` (string) at envelope level for
  creates. It is **correlation only** — the server returns the canonical
  `entity_id` in the outcome.
- **No chains**: the queue has no dependency model, so a queued edit that
  references a not-yet-reconciled temp id is NOT invented. After an offline
  create reconciles, subsequent edits use the canonical id online.
- No distributed ID architecture is introduced.

### 2.13 Workspace semantics

- Each envelope carries `workspace_id` **snapshotted at enqueue** from the
  domain context the mutation was made under — replay never depends on the
  currently-active localStorage Workspace.
- The payload fields the online endpoint accepts (`workspace_id`,
  `program_id`, `goal_id`) pass through to the same use cases, which own
  precedence and validate ownership.
- Server verifies user and workspace ownership on every replay (use cases +
  `ResolveWorkspaceContext` behavior).
- Cross-workspace Today/Week/Month and user-global Hard Landscape are
  unchanged.

### 2.14 Web MutationQueue integration

Wire the **existing** IndexedDB `MutationQueue` (not replaced):

- `HttpMutationApplier` now posts the envelope to `/sync/reconcile` and maps
  outcomes: `applied` → applied; `conflict` → conflict (keeps local intent);
  `rejected`/`expired` → permanent; network/429/5xx → retryable.
- Stores for allowlist mutations (`task`, `note`) generate the operation_id
  and:
  - **online** → send with `X-Operation-Id` (idempotency-recorded);
  - **offline** (`navigator.onLine === false` or `OFFLINE` error) → enqueue the
    envelope (persisted before any local success), plus optimistic/local state
    where the existing UX already permits it (none is added for creates);
  - **network error mid-flight** → enqueue the same operation_id (safe replay).
- Reconnect drain already exists (`AuthHost` 'online' → `syncController.sync()`);
  a boot-time drain is added when the queue holds pending items and the
  network is online.
- Applied items are removed only after server acknowledgement; conflicts and
  rejections are surfaced (not endless-retried).
- After a successful drain, affected stores are rehydrated from the canonical
  responses (task/note lists, today) — bounded refresh, no full-page reload.

### 2.15 Client queue state machine

Existing statuses map to the conceptual set:

| conceptual | existing |
|---|---|
| QUEUED | `queued` |
| SYNCING | `syncing` |
| APPLIED | removed from the queue |
| CONFLICT | `failed_permanent` + conflict marker (kept locally, `resolve()` discards) |
| REJECTED | `failed_permanent` + actionable error |

`failed_retryable` remains a retry-pending state (transient), never conflated
with permanent failure. The queue's `syncing` flag prevents two simultaneous
drain loops.

### 2.16 Online/offline transitions & auth

- online→offline, offline→online, reload-with-queued, close-with-queued:
  the durable IndexedDB queue survives all of them; the boot-time drain handles
  reload.
- 401 during drain: the client preserves queued intent, surfaces
  "Sign in again to continue syncing", and stops draining — but **never**
  executes under a different authenticated user. On the next authenticated
  session the queue is keyed to that user's session; a user switch does **not**
  replay the previous user's queue (queue reads are scoped to the authenticated
  session identity at drain time, and the server scopes by `(user_id,
  operation_id)`).
- **Bug fix**: `auth/store.ts` clears the token on any `restoreSession`
  failure — changed to clear only on 401/security failures, keeping the session
  on `OFFLINE` network errors (offline reload no longer logs the user out).

### 2.17 Read cache / canonical rehydration

The Today read cache and other offline reads are **not** expanded to every
surface. Scope: after successful reconciliation, canonical server responses
rehydrate the touched stores; Today refreshes once after a drain (bounded
refresh via the existing `today.load`). No full-page reload as the only
correctness mechanism.

### 2.18 UX

Existing design system only (no Stitch). Minimum visible states (aggregate, not
per-op toast spam — the shell badge `SyncStatusPanel` already exists and is
reused):

- Offline / Saved on this device / Waiting to sync / Syncing / Synced /
  Some changes need review / Could not sync this change / Sign in again to
  continue syncing.
- "Synced"/"Saved" is only claimed after server acknowledgement.
- Conflict → "Some changes need review" with a way to discard the local change
  (`queue.resolve`) — never color-only; text + status semantics + keyboard
  reachable (existing panel patterns).

### 2.19 Mobile disposition — Option B (deferred durable queue, shared protocol)

NativePHP screens currently show `offline`/`queued` labels with **no durable
persistence** (CaptureScreen's queued capture is a component-property draft,
lost on navigation). Decision: **Option B** — keep mobile offline mutation
persistence for the Android production-hardening phase, but the **server
protocol (envelope + ledger + `/sync/reconcile`) is the mobile contract too**
and is reusable unchanged. Mobile docs (`mobile-architecture.md`,
`offline-sync.md`) are updated truthfully: mobile is **not** offline-capable
today beyond the shell label. This does not block the server/web blocker.

### 2.20 Security

- Authentication on every reconcile request (Sanctum).
- User ownership + Workspace ownership re-verified per operation by the real
  use cases.
- Operation-type allowlist enforced server-side; no dynamic dispatch from
  client strings; no arbitrary URL replay.
- Payload validation reusing the online validator rules; no mass-assignment
  bypass.
- Bounded batch (≤ 50) and bounded payloads (per-op ≤ 64 KB, total ≤ 512 KB);
  rate limiting on the reconcile endpoint (`throttle:api` + a dedicated limiter).
- Ledger stores `payload_hash` + bounded `result` only — **not** full note/task
  content (privacy: minimum necessary).
- `operation_id` is an idempotency identity, never an authorization token.
- `client_created_at` is never used for write precedence.

## 3. Consequences

- **+** BLOCKER-OFFLINE-01 resolved at the server+web level: authoritative
  ledger, idempotent replay, conflict semantics, real web queue integration,
  honest docs.
- **+** Offline and online paths converge on the same use-case authority.
- **+** Existing optimistic versions and 409 semantics are reused; no LWW, no
  CRDT, no Redis, no event sourcing, no generic replay proxy.
- **−** Three migrations (`offline_operations`, plus task-update version check
  is column-free) and a small set of controller touch-points for the allowlist.
- **−** The web queue is now real: offline allowlist mutations enqueue instead
  of failing; this is a behavior change from today's "OFFLINE error".
- **Risks controlled:** double-apply (unique ledger + payload hash), silent
  overwrite (version conflicts), cross-account replay (user scoping),
  unbounded ledger (retention), spam (aggregate UX), binary bloat (hash-only),
  offline schedule corruption (schedule mutations excluded).

## 4. Out of scope (explicit)

CRDT/event-sourcing/replication infrastructure, Redis, WebSocket sync engine,
background sync API (Background Sync not required — boot + reconnect drains),
mobile durable queue (deferred per §2.19), goal/milestone/program offline,
quick capture offline, canvas offline, subtask toggle offline, offline read
caching beyond the current Today scope, SRS v3 / design / architecture
convergence.