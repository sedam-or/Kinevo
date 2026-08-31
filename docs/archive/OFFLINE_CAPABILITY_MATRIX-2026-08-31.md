# Offline Capability Matrix — 2026-08-31 (ADR-017)

Every client-reachable mutation classified for offline support. Basis: the
web offline audit (server/resources/js API calls) and the NativePHP/mobile
surface. `OFFLINE_SUPPORTED` = may reconcile via `/sync/reconcile` (ADR-017
§2.1). `ONLINE_ONLY` = explicit exclusion. `DECISION_REQUIRED` = resolved by
ADR-017 (below) and re-listed with the chosen classification.

| Feature | Endpoint / use case | Entity | Versioned? | Idempotent? | Server IDs? | Binary? | Security/billing? | AI-dependent? | Cross-entity txn? | Offline classification | Reason |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Task create | `POST /tasks` / `CreateTaskUseCase` | task | no | ledger | yes | no | no | no | workspace resolve | **OFFLINE_SUPPORTED** | Simple create; ledger dedup; temp id correlation |
| Task update | `PUT /tasks/{id}` / `UpdateTaskUseCase` | task | yes (NEW `base_version` per ADR-017 §2.11) | ledger + version | no | no | no | no | — | **OFFLINE_SUPPORTED** | Optimistic version conflict → review |
| Task status / complete | `POST /tasks/{id}/status` / `SetTaskStatusUseCase` | task | state machine | semantic (already-in-target = applied) | no | no | no | no | activity+progress logs (same use case) | **OFFLINE_SUPPORTED** | Completion is the key offline action; semantic idempotency |
| Task partial-complete | `POST /tasks/{id}/partial-complete` | task | progress | partial | no | no | no | no | progress events | ONLINE_ONLY | Progress accounting vs newer state; deferred |
| Task lock/unlock | `POST /tasks/{id}/assignment/lock\|unlock` / `SetAssignmentLockUseCase` | assignment | version | partial | no | no | no | no | schedule version | ONLINE_ONLY | Schedule-state mutation (ADR-016 authority) |
| Task auto-swap | `POST /tasks/{id}/auto-swap` | assignment | — | — | no | no | no | no | schedule version | ONLINE_ONLY | Schedule mutation |
| Subtask create | `POST /tasks/{id}/subtasks` / `AddSubtaskUseCase` | subtask | no | ledger | yes | no | no | no | task ownership | **OFFLINE_SUPPORTED** | Simple create under existing task |
| Subtask toggle | `POST /tasks/{id}/subtasks/{sid}/toggle` / `ToggleSubtaskUseCase` | subtask | — | **no** (flip) | no | no | no | no | — | ONLINE_ONLY | Flip semantics are not idempotent; no desired-state contract in queue (ADR-017 §2.1) |
| Note create | `POST /notes` / `CreateNoteUseCase` | note | no | ledger | yes | no (rich text JSON in payload) | no | no | workspace resolve | **OFFLINE_SUPPORTED** | Create; ledger dedup |
| Note update | `PATCH /notes/{id}` / `UpdateNoteUseCase` | note | yes (base_version, existing) | ledger + version | no | no | no | no | — | **OFFLINE_SUPPORTED** | Existing optimistic 409 reused |
| Quick capture | `POST /quick-capture` / `QuickCapturePlacementUseCase` | task + assignment | — | partial | yes | no | no | no | **schedule placement + schedule_version bump** | ONLINE_ONLY | Placement writes the canonical schedule; offline replay could silently collide with newer reality (ADR-017 §2.1). Create a task offline, use Sync Now (ADR-016) online |
| Schedule draft apply | `POST /schedule/draft/apply` | assignment(s) | schedule_version | ledger (use case) | no | no | no | no | multi-assignment + history | ONLINE_ONLY | Schedule mutation |
| Schedule reschedule apply | `POST /schedule/reschedule/apply` | assignment(s) | schedule_version | ledger | no | no | no | no | multi-assignment + history | ONLINE_ONLY | Schedule mutation |
| Schedule Sync Now | `POST /schedule/sync` | read-only diff | schedule_version | n/a | no | no | no | no | — | ONLINE_ONLY | Read + review workflow; distinct from offline reconcile (ADR-016 vs ADR-017) |
| Mini / Emergency pause | `POST /schedule/mini-pause` / `emergency-pause` | assignment(s) | schedule_version | partial | no | no | no | no | schedule + pause events | ONLINE_ONLY | Schedule mutation |
| Schedule overrides | `POST/PATCH/DELETE /schedule-overrides` | override | — | partial | yes | no | no | no | effective landscape | ONLINE_ONLY | Reality mutation (ADR-015 authority) |
| Break mode | `/break*` | break period | — | partial | no | no | no | no | — | ONLINE_ONLY | Time/state-sensitive; deferred |
| Boost target | `/boost*` | boost | — | partial | no | no | no | no | — | ONLINE_ONLY | Time-sensitive; deferred |
| Goal create/update | `POST/PUT /goals` | goal | version | ledger possible | yes | no | no | no | workspace | DECISION_REQUIRED → **ONLINE_ONLY (deferred)** | Future allowlist extension (ADR-017 §2.1) |
| Milestone / Program mutations | `/milestones`, `/programs` | milestone/program | version | possible | yes | no | no | no | workspace | DECISION_REQUIRED → **ONLINE_ONLY (deferred)** | Future allowlist extension |
| Canvas save | `PUT /canvases/{id}` | canvas document | base_version (existing 409) | ledger possible | no | rich JSON (can be large) | no | no | — | ONLINE_ONLY | Rich/large content; canvas has its own in-memory autosave; binary assets excluded (invariant 14) |
| KRS/ICS import confirm/discard | `POST /imports/...` | hard landscape | — | staged confirm | no | PDF/ICS binary | no | no | multi-row | ONLINE_ONLY | Binary + bulk reality mutation |
| Attachment upload | `/attachments` | asset | — | — | yes | **yes** | no | no | storage | ONLINE_ONLY | Binary excluded from JSON ledger (invariant 14) |
| Auth / profile | `/auth/*`, `/profile` | user/profile | version | — | no | no | **yes** | no | — | ONLINE_ONLY | Security-sensitive (invariant 15) |
| Billing / subscription | `/billing/*` | subscription | operation_id (existing) | yes (server-generated) | no | no | **yes** | no | payment gateway | ONLINE_ONLY | Billing-sensitive (invariant 15) |
| AI operations | `/ai/*` | ai_run/proposal | — | server-generated | yes | no | no | **yes** | provider calls | ONLINE_ONLY | AI-dependent (invariant) |
| Notifications read | `POST /notifications/{id}/read` | notification | — | n/a | no | no | no | no | — | ONLINE_ONLY | Read-ahead safe to lose; keep online for boundedness |

## Web queue integration scope (ADR-017 §2.14)

Stores wired to the MutationQueue for the OFFLINE_SUPPORTED set: **task store**
(create, update, status), **note store** (create, update). All other stores
keep their online-only calls (fail visibly offline — unchanged today).

## Mobile disposition (ADR-017 §2.19)

All NativePHP mutations: **ONLINE_ONLY** this epic. `queued`/`offline` shell
labels remain status-only; durable persistence deferred to the Android
production-hardening phase. The server protocol (envelope + ledger +
`/sync/reconcile`) is the future mobile contract, unchanged.