# Module `sync` (offline operations)

Kind: toggleable (default on). Depends on: `tasks`. Optional: `knowledge` (note operations). Tables: `offline_operations`. Prefix: `SYN`.
Client counterpart: `08-frontend.md` §Offline. Decisions: D-34, D-35. Ported from legacy ADR-017
(`legacy-v0:docs/adr/ADR-017*`, `legacy-v0:server/app/Application/OfflineSync/*`) with the fixes listed in §10.

**Principle.** IndexedDB is a cache and a queue, never the source of truth. The server reconciles every
queued operation through the same use cases as online writes. No last-write-wins, ever.

## 1. Permissions

`sync.reconcile.execute`, `sync.ledger.read` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `POST /sync/reconcile` `{ protocol_version: 1, operations: [Op…] }` | 200 `{ results: [Result…] }` in request order | `sync_protocol_unsupported` 422, `sync_batch_too_large` 422, `sync_payload_too_large` 413, `unauthenticated` 401, `rate_limited` 429, `module_disabled` 404 |
| `GET /sync/operations/{operation_id}` | 200 stored `Result` (for recovering a lost response) | `not_found` (not yet applied or pruned) |

**Op**: `{ operation_id: uuid, op_type, entity_id?: uuid, base_version?: int, payload: object, workspace_id?: uuid, client_created_at?: instant }`.
`client_created_at` is informational (display, diagnostics) and is **never** used for ordering or conflict decisions.

**Result**: `{ operation_id, status: "applied"|"conflict"|"rejected"|"retry", http_status: int, code?: string, replay: bool, entity?: {type,id,version,…current representation…}, error?: { code, details } }`.
`http_status` is the status the same call would have returned online (200/201, 409, 404, 422, 503 …), so clients share one error mapper.

Supported `op_type` (closed list; anything else → `rejected` `op_unsupported`):

| op_type | Same use case as | Notes |
|---|---|---|
| `task:create` | `POST /tasks` | `entity_id` = client-generated UUID |
| `task:update` | `PATCH /tasks/{id}` | needs `base_version` |
| `task:status` | `POST /tasks/{id}/status` | user-actor transitions only (TASK-FR-04); needs `base_version` |
| `subtask:create` | `POST /tasks/{id}/subtasks` | `entity_id` client-generated; `payload.task_id` |
| `subtask:update` | `PATCH /subtasks/{id}` | needs `base_version` |
| `note:create`, `note:update` | notes endpoints | only if `knowledge` enabled |

Not offline-capable in 1.0 (require connectivity): focus sessions, schedule apply/sync, landscape edits and imports, goals/milestones, canvas, AI, policies, account. (Canvas offline is **not** supported: the legacy spec claimed it, the legacy server did not implement it.)

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| SYN-FR-01 | **Ledger.** For `(account, operation_id)`: if a row exists and `payload_hash` matches the canonical hash of `{op_type, entity_id, base_version, payload, workspace_id}` ⇒ return the stored result with `replay=true` and do **not** execute. If the hash differs ⇒ `rejected` / `operation_id_reused` (422). | Tests: replay equal; replay different. |
| SYN-FR-02 | **Atomic execution.** A new operation executes its use case and inserts its ledger row in **one transaction**. A concurrent duplicate hits the unique index; the loser re-reads the row and replays (no error, no double-apply). | Concurrency test: 20 parallel identical ops ⇒ one execution, 19 replays. |
| SYN-FR-03 | **What is recorded.** `applied`, `conflict` and `rejected` outcomes are recorded (they are deterministic). Transient failures (database unavailable, serialization failure, timeout, internal error) return `status="retry"`, `http_status` 503/500, **no ledger row**, so the client can resend the same `operation_id`. (Legacy recorded transient errors as `rejected` permanently.) | Fault-injection test. |
| SYN-FR-04 | **Conflicts, not overwrites.** `base_version` mismatch ⇒ `conflict`, `code=version_conflict`, `http_status=409`, `entity` = the server's current representation. Illegal status transition ⇒ `conflict`, `code=invalid_transition`. | Tests. |
| SYN-FR-05 | **Dependencies in a batch.** Operations run sequentially in array order. If an operation does not end `applied`, every later operation in the same batch whose `entity_id` or `payload.task_id`/`payload.goal_id` references the same entity (or an entity created by the failed op) is `rejected` with `dependency_failed` (422) — recorded in the ledger. | Test: failed `task:create` ⇒ its `subtask:create` is `dependency_failed`. |
| SYN-FR-06 | **Client-generated ids.** Create ops carry `entity_id` (UUID, any version). If it already exists in the caller's account with an identical creation payload ⇒ treated as replay of the create (`applied`, `replay=true`); different payload ⇒ `rejected` `entity_id_taken`. If it collides with another account's row the result is the same `entity_id_taken` (no existence leak). | Tests. |
| SYN-FR-07 | **Limits (policies).** Batch ≤ `sync.batch.max_operations`; each op payload ≤ `sync.operation.max_bytes`; request ≤ `sync.request.max_bytes`. Over limits: 422/413 as in the table; nothing executes. | Boundary tests. |
| SYN-FR-08 | **Authorisation.** Ops execute as the authenticated account; `workspace_id`, `goal_id`, `entity_id` referencing other accounts ⇒ `rejected` `not_found` (404). Archived workspace ⇒ `rejected` `workspace_archived`. | Abuse tests. |
| SYN-FR-09 | **Module gating.** With `sync` disabled, `/sync/*` returns 404 `module_disabled` and the web app sends mutations directly (with `Idempotency-Key`) and shows an "offline editing is off" state when disconnected. | Module-matrix test. |
| SYN-FR-10 | **Pruning.** Job `sync.ledger.prune` (daily) deletes rows older than `sync.ledger.retention_days`. An operation older than retention that is resent is executed again if its entity/version still allows; for creates, SYN-FR-06 makes the replay safe; for updates, `base_version` prevents double-apply. Clients purge their own queue entries after receiving a final result. | Test with injected clock. |
| SYN-FR-11 | **Rate limit** `ratelimit.sync_per_minute` per account; exceeded ⇒ 429 with `Retry-After` for the whole request (nothing executed). | Test. |

## 4. Client queue contract (normative for `web/` and the Android shell)

| ID | Requirement |
|---|---|
| SYN-C1 | The queue is an IndexedDB store keyed by `operation_id`. One user intent ⇒ one `operation_id` generated **once** and reused on every retry (never regenerated after a network error). |
| SYN-C2 | Item states: `pending` → `in_flight` → (`done` removed) \| `needs_attention` (conflict or rejected). A crash while `in_flight` returns the item to `pending` on startup; the server's ledger makes the resend safe. |
| SYN-C3 | Drain: serial batches in FIFO order; triggered by enqueue (when online), `online` event, window focus, every 60 s while items are pending, and after login. Network error / 5xx / 429 / per-op `retry` ⇒ exponential backoff with jitter (base 2 s, cap 5 min), honouring `Retry-After`. 401 ⇒ one refresh attempt, then drain again; refresh failure ⇒ stop and show sign-in. |
| SYN-C4 | When the server returns `conflict`, the UI shows a conflict card with "Your change", "Current version", and the actions **Keep current** (drop op) and **Reapply mine** (new `operation_id`, `base_version` = current). `rejected` offers **Discard** and, for validation errors, **Edit**. |
| SYN-C5 | Optimistic UI applies the intent to the local cache immediately and marks the item "syncing"; on `applied` it replaces the local entity with the server's; on failure it shows the card and keeps the local value visibly flagged until resolved. |
| SYN-C6 | Logout keeps the queue for the same account; logging in as a different account purges it after a confirmation. The queue is plain IndexedDB (device-level protection only) — a documented residual risk (`10-security-privacy.md`). |
| SYN-C7 | The queue length and oldest item age are visible in the UI ("3 changes waiting to sync"). |

## 5. State machine — operation (server)

| State | Meaning | Next |
|---|---|---|
| (unseen) | no ledger row | execute → `applied` \| `conflict` \| `rejected` (recorded) or `retry` (not recorded) |
| `applied` / `conflict` / `rejected` | stored | replay returns the stored result (same `http_status`) |

## 6. Data flow — reconcile

```
POST /sync/reconcile  →  validate envelope (protocol, sizes, shape) → 4xx before any execution
for op in operations (in order):
   if depends on failed entity → record rejected(dependency_failed)
   BEGIN (account context)
     SELECT ledger WHERE operation_id  → hit: compare hash → replay | rejected(reused)   [COMMIT]
     authorise + validate + run use case (same code as online)
     INSERT ledger (outcome)   ← unique violation ⇒ ROLLBACK, re-read, replay
   COMMIT      transient error ⇒ ROLLBACK, result status=retry (no ledger row)
respond 200 {results}  (always 200 for processed batches; per-op http_status carries semantics)
```
Why HTTP 200 for the batch: partial success is normal; the per-operation `http_status` preserves the 409/404/422 semantics for clients (contradiction C5 in `00-verdict.md` resolved by making this explicit and consistent).

## 7. Events / jobs

Emits none (the executed use cases emit their own events). Job `sync.ledger.prune`.

## 8. Validation

`operation_id`: UUID string; `op_type`: closed enum; `payload`: object validated by the target use case's request schema (unknown fields rejected); `base_version`: int ≥ 1 where required.

## 9. Abuse cases

| ID | Case | Expected |
|---|---|---|
| SEC-AC-25 | Same `operation_id`, different payload | `rejected` `operation_id_reused`, nothing executed |
| SEC-AC-26 | `operation_id` of another account | executes normally (ledger is per account); no information about the other account |
| SEC-AC-27 | 10 000-op batch / 100 MB body | rejected before parsing the whole body (size limit on the stream) |
| SEC-AC-28 | `entity_id` belonging to another account in `task:update` | `rejected` `not_found` |
| SEC-AC-29 | Replay storm of an `applied` op | always `replay=true`, constant work |

## 10. Differences from legacy (intentional)

1. Client reuses `operation_id` across retries (legacy minted a new id on network failure ⇒ possible double-apply).
2. Transient errors are not ledgered (legacy stored `rejected` permanently).
3. Concurrent first requests replay cleanly (legacy hit the unique index).
4. Per-operation `http_status` replaces the implicit "always 200" with unspecified meaning.
5. Canvas offline is out of scope instead of half-claimed.
6. A single path: with `sync` on, all supported mutations go through the queue; the legacy "header-based online path" is removed.
