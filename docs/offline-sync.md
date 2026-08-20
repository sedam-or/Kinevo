# Kinevo — Offline & Synchronization

### Principle
Offline mode increases continuity, but PostgreSQL remains authoritative.

### Offline scope
MVP offline MUST support:
- Today baseline/cache;
- current-day task interactions in defined scope;
- task notes where defined;
- Quick Capture;
- knowledge/canvas mutations where explicitly implemented;
- mutation queue persistence through tab close.

### Local storage
IndexedDB stores:
- cache records;
- mutation queue;
- operation metadata;
- local snapshots;
- sync cursor/version information.

### Mutation envelope
```json
{
  "operation_id": "uuid",
  "entity_type": "task",
  "entity_id": "uuid",
  "operation_type": "update",
  "client_timestamp": "...",
  "base_version": 17,
  "payload": {},
  "payload_hash": "..."
}
```

### Queue semantics
- append mutation;
- persist before reporting local success;
- sync FIFO where possible;
- collapse safe repeated mutations where allowed;
- retry transient failures;
- surface permanent failures.

### Conflict strategy
Baseline remains last-write-wins for offline task mutations where specified, but entity-specific version protection MAY override this where silent loss is unsafe (for example Canvas version conflict).

### Sync state machine
```text
Idle
 → Offline
 → Queued
 → Syncing
 → Applied
 → FailedRetryable
 → FailedPermanent
```

### Visible states (TASK-115)
The UI presents these user-visible states and their meaning:
- **Online** — connected; mutations reach the server directly.
- **Offline** — no connection; changes are stored locally and sync on reconnect.
- **Queued** — changes are saved locally and waiting to sync.
- **Syncing** — queued changes are being sent to the server.
- **Saved** — a sync pass drained the queue successfully (persisted server-side).
- **Conflict** — a queued change conflicts with server state; local data is
  preserved and must be reviewed (never silently overwritten, SRS §9.4).
- **Retrying** — a transient sync failure; the queue will retry.
- **Failed** — a change could not sync; the local copy is preserved.

Mapping from the queue state machine (offline-sync.md §Sync state machine) and
the network status into these visible states is owned by
`offline/sync-status.ts` (`SyncStatusController`,
`mapQueueStateToSyncState`), which bridges the general MutationQueue into the
shell store. `offline/http-applier.ts` translates a queued envelope into the
matching API mutation and maps the outcome (applied / 409 conflict /
offline-5xx-429 retryable / other-4xx permanent). Unsupported operations are a
permanent failure that preserves the local copy — a failed sync MUST NOT
silently discard local data (§Failure safety).

### Service Worker
Responsibilities:
- cache app shell;
- enable offline navigation where in scope;
- intercept network requests only when contractually safe;
- not become a second business-logic engine.

### Sync flow
```text
Connection lost
 ↓
Local mutation persisted
 ↓
UI marks queued
 ↓
Connection restored
 ↓
Sync worker
 ↓
POST/PATCH operation
 ↓
Server validates ownership/version/idempotency
 ↓
Apply or conflict
 ↓
Client updates cache
```

### Failure safety
A failed sync MUST NOT silently discard local data. Permanent conflict requires user-visible reconciliation or safe preservation.

---

