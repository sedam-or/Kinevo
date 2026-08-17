# LIFESYNC OS — Offline & Synchronization

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

