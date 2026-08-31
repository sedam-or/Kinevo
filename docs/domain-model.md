# Kinevo — Domain Model

> STATUS: AUTHORITATIVE (P29, 2026-08-31). Canonical domain authority under the
> SRS. Describes domain MEANING — aggregate boundaries, ownership, scope,
> invariants — not a mechanical class inventory (implementation lives in
> `server/app/Domain/*`). Supersedes the v2-era domain-model doc (archived
> content migrated). Workspace is first-class. Scheduling semantics: ADR-015/016;
> offline: ADR-017. Code is evidence, not intent.

## 1. Core principle

One deployable Laravel application with explicit domain/application/infrastructure
boundaries. Domain objects own business meaning; application use cases orchestrate
transactions; infrastructure implements persistence/providers. HTTP and UI never
contain scheduling or domain rules. Every mutating flow crosses an explicit
transaction boundary (or a documented compensating action).

## 2. Shared kernel

- **UserId** — the single-owner boundary; every aggregate is user-scoped (SRS
  single-user model).
- **WorkspaceId** — contextual organization boundary (workspace-scoped aggregates
  only; Hard Landscape and schedule placements are never workspace-owned).
- **ScheduleVersion / OperationId** — optimistic concurrency + idempotency value
  objects.
- **Money/PriceCatalog** — commercial value objects (whole-Rupiah minor units).

## 3. Domains

### 3.1 Identity
Owns: User, authentication tokens, profile settings, registration (first-owner
rule). Scope: global. Invariants: registration only for the first owner; tokens
are revocable credentials. No workspace knowledge.

### 3.2 Workspace (first-class)
Owns: Workspace aggregate (name, slug, accent, type, status; default workspace
unarchivable). Scope: global entity, scopes other aggregates. Invariants:
scoped entities carry workspace_id from creation; unassigned legacy rows adopted
once; archive never cascades to execution surfaces (Today/Week/Month stay
cross-workspace). Canonical semantics: `docs/product/workspace-model.md`.

### 3.3 Goals
Owns: Goal aggregate (title/outcome, horizon, deadline, status), Milestone
child aggregates, Program aggregate. Scope: workspace-scoped. Invariants:
milestones belong to exactly one goal; program/task linkage keeps context
continuity; status transitions follow the state machine
(`docs/ux/interaction-states.md`). AI breakdown proposals reference the goal but
never mutate it before approval.

### 3.4 Tasks
Owns: Task aggregate (status, priority, estimate, sacred-anchor flag),
Subtask, TaskAssignment (placement), ExecutionSession (timer), boost/pause
states. Scope: workspace-scoped; assignments are personal-reality (no
workspace_id). Invariants: status machine is the single transition authority
(`TASK_TRANSITIONS`); completion records ActivityEvent + ProgressEvent in the
same transaction; locked placements (`assignment_locked`) are immovable by
scheduler/rescheduler.

### 3.5 Scheduling (Effective Landscape)
Owns: **HardLandscapeEvent** (global fixed commitments; recurrence rules),
**ScheduleOverride** (Permanent Shift / One-Time Exception with
`cancels_occurrence`), **EffectiveLandscapeResolver** (base occurrence ←
recurrence expansion → override resolution: exception > latest shift > base →
effective occurrence → effective landscape for Today/Week/Month/scheduler/ICS),
**ScheduleAssignmentHistory** (superseded placements archived in the same
transaction as every mutation), **ScheduleDraft** (lifecycle: pending → reviewed
→ applied/discarded; never auto-applied), **SchedulerRun** (weekly prepare +
Sync Now; run locks; deterministic inputs→outputs).
Invariants: weekly automation calculates only; Sync Now re-proposes, never
silently mutates; UNTIL is inclusive UTC-normalized; COUNT counts from series
start; locked tasks and Sacred Anchors are hard constraints. Full pipeline:
`docs/scheduling-engine.md` (ADR-015/016).

### 3.6 Knowledge
Owns: Note (Tiptap document, base_version conflict contract), KnowledgeLink
(typed: supports/references/derived_from/evidence_for/related_to; target
polymorphic Goal/Task/Milestone/Program/Canvas/Note), attachments. Scope:
workspace-scoped. Invariants: editor boundary (ADR-009) — Tiptap owns editing
semantics only; links are typed and unique per pair; conflict protection via
optimistic versioning with explicit reload-to-reconcile.

### 3.7 Canvas
Owns: Canvas (document JSON, version, archive state) + CanvasFiles. Scope:
workspace-scoped. Invariants: Excalidraw owns drawing only behind the
CanvasAdapter boundary (ADR-010); save policy = autosave with version conflict
detection (stale→conflict→explicit reload); offline canvas edits follow the
offline operation contract; no business semantics inside the island.

### 3.8 Analytics / Progress
Owns: ProgressEvent (closed type set: task_completed, milestone_advanced,
milestone_completed, evidence_attached, experiment_recorded, goal_progress —
manual types restricted), FocusSession, RechargeSession, Work-Life aggregates.
Scope: user-global with optional workspace filter (`?workspace_id` accepted;
UI wiring TARGET). Invariants: progress events are domain-derived from status
changes (not client-authored); analytics never contain note/task content.

### 3.9 AI
Owns: AIProposal (entity: type, payload, status pending/accepted/rejected,
target workspace), AiProviderConfig (single-row settings, encrypted credential),
AiRun (invocation + cost ledger; hosted/BYOK split), AiCreditGuard (request
firewall). Invariants (ADR-011): providers behind one abstraction
(disabled/ollama/openai-compatible/mock); structured output validated → domain
validated → explicit approval; no auto-accept path; AI never becomes scheduling
authority; proposals inherit the target entity's workspace.

### 3.10 Billing / Subscriptions
Owns: Subscription (plan code, state, period), Entitlement (config matrix
enforced via EntitlementService), payment provider port (Midtrans; sandbox
CURRENT, production P33), webhook processing (signed + idempotent). Invariants:
PAYMENT ≠ SUBSCRIPTION ≠ ENTITLEMENT ≠ AI USAGE; redirects never grant
entitlement; effectivePlanCode maps retired tiers to catalog defaults; ledger
truth is Kinevo-owned. Commercial meaning: `docs/product/commercial-model.md`.

### 3.11 Notifications
Owns: Notification aggregate (unread/today/earlier grouping), read state,
provider port (TARGET; Gotify reference-only). Scope: global with workspace
context where relevant.

### 3.12 Offline Reconciliation
Owns: **OfflineOperation** ledger (operation UUID, kind, payload, base_version,
outcome), reconcile endpoint use case, idempotency + conflict resolution. Scope:
per-user ledger; target entities may be workspace-scoped. Invariants (ADR-017):
same operation_id + same payload replays safely (response-loss protection);
same id + different payload is rejected; base_version optimistic conflicts
return stable 409; the client queue (IndexedDB) is a cache — the server is
canonical; no LWW; client clocks never decide precedence; bounded mutation
allowlist (task create/update/status, subtask create, note create/update).
Durable mobile offline reuses this exact protocol in P36 — no second protocol.

### 3.13 Assets (boundary only)
Owns: attachment records linked to evidence. Storage pipeline (Uppy → validation
→ Pic Smaller → AssetStorage → object storage) is TARGET P31; no large binaries
inside Note/Canvas JSON.

## 4. Cross-domain dependency rules

- Application use cases are the only transaction orchestrators.
- Scheduling depends on Hard Landscape + Tasks + capacity signals; never on AI.
- AI depends on Goals/Knowledge/Canvas read models for context; writes only via
  approved proposals.
- Offline reconciliation reuses the SAME application use cases as online
  mutations (no divergent second path).
- Billing depends on nothing product-side for truth; entitlement checks wrap
  use cases.
- Workspace scoping is enforced at repository level for scoped aggregates.

## 5. Global vs workspace scope summary

Workspace-scoped: Goal, Milestone, Program, Task, Subtask, Note, KnowledgeLink,
Canvas, Attachment. Global: User, Workspace, HardLandscapeEvent,
ScheduleOverride, ScheduleAssignmentHistory, ScheduleDraft, SchedulerRun,
TaskAssignment (personal reality), ProgressEvent (user-global, workspace
attribute optional), AIProviderConfig, AiRun, Subscription, OfflineOperation
(target-scoped), Notification.
