# ADR-016 — Scheduler Trigger, Sync Now, and Draft Approval Lifecycle

- **Status:** ACCEPTED (2026-08-31)
- **Supersedes:** none
- **Amends:** ADR-015 (Effective Landscape authority and locked-task immutability are preserved unchanged)
- **Resolves:** BLOCKER-SCHED-01 (weekly job + run lock + Sync Now absent; FR-27/FR-29 trigger semantics)
- **Companion:** `docs/convergence/PRE_CONVERGENCE_BASELINE.md` (blocker register), `docs/scheduling-engine.md`

## 1. Context

The scheduling engine is deterministic and manual-only:

- Draft generation (`POST /schedule/draft`) and reschedule proposals
  (`POST /schedule/reschedule`) are **ephemeral** — computed per request,
  client-held JSON, applied by echo with `base_version` (409 on stale base).
- There is **no weekly trigger**, **no manual Sync Now**, **no run lock**, and
  **no scheduling reaction** to Hard Landscape / override / import mutations
  (`docs/audit/PRODUCTION_READINESS_GAP_2026-08-30.md` — BLOCKER-SCHED-01).
- `scheduler_runs` is telemetry-only (single writer: `eod:reconcile`).
- Queue infrastructure (`QUEUE_CONNECTION=database`, idle worker container)
  exists but zero application code dispatches jobs.
- `Cache::lock` (database lock store) is a proven in-repo facility
  (`AiController` generate throttle).
- Drafts carry no persisted state; "Cancel" is a client-side clear.

FR-27/FR-29 require automatic weekly planning preparation and manual
recalculation without ever auto-applying movement of accepted work.

## 2. Decision

### 2.1 Automatic weekly behavior — ADOPTED (persisted draft, never auto-apply)

A console command `schedule:prepare-weekly` runs **daily at 04:00 UTC** with
`withoutOverlapping()`. For each user whose **profile-local date is Monday**
(their local week just started), it computes a deterministic planning draft for
the local Monday–Sunday horizon and **persists** it as a `schedule_drafts` row
(`source=weekly`, `generated_for_week=<local Monday>`), status `pending`.

- The draft proposes placement of **unscheduled flexible tasks only** into
  effective free slots (existing generator semantics: accepted placements and
  locked work occupy slots; Sacred Anchor first when present).
- It **never mutates** accepted placements. Apply happens only through the
  existing explicit apply flow (§2.5).
- Idempotency: unique `(user_id, source, generated_for_week)`; a re-run skips
  users that already have a pending (non-stale) draft for that week.
- If a **stale** pending weekly draft exists for the new week anchor
  (base_version no longer current), the new run **supersedes** it
  (status `superseded`) and stores a fresh draft. Older pending weekly drafts
  from previous weeks are superseded during the same pass (housekeeping).
- Users with **no schedulable tasks** get no draft (no empty notification).
- Failure per user is logged + recorded in `scheduler_runs` (aggregate run
  record; per-user failure record) and never aborts the loop over other users.
  Retry is safe (idempotent by week anchor).

Rationale for daily-schedule + local-Monday gate: a single global weekly cron
cannot hit all timezone week starts; a daily idempotent pass with a local-week
gate is timezone-safe and bounded (users without a fresh local Monday are
skipped in O(1)).

### 2.2 Manual Sync Now — ADOPTED

`POST /schedule/sync` (new endpoint, `SyncNowUseCase`):

1. Load the **current Effective Landscape** (ADR-015 resolver) and accepted
   placements for a bounded horizon: client `from`/`to` (≤ 14 days) or default
   **profile-local current Monday–Sunday**.
2. Run the **same deterministic diff** as the rescheduler
   (`DynamicRescheduler::propose` — no second rescheduler is introduced).
3. Return a **state + diff**, never a silent write:

| `status`            | Meaning                                                              |
|---------------------|----------------------------------------------------------------------|
| `no_changes`        | Empty diff; schedule already consistent with reality.               |
| `proposal`          | Diff found; `proposal` payload identical in shape to the existing reschedule proposal (`base_version`, `new_version`, `moves[]`, `conflict_task_ids`). |
| `run_in_progress`   | Another Sync Now / weekly prepare holds the user's scheduling lock (§2.4). |

Apply is always the existing explicit flow: client echoes the proposal to
`POST /schedule/reschedule/apply` (409 on stale base — unchanged). Sync Now
MUST NOT force-write, refresh-only, call AI, re-import calendars, or discard
locked work.

### 2.3 Reality-change trigger — ADOPTED (bounded, lazy, review-state)

Hard Landscape create/update/delete, KRS/ICS import confirm, and Schedule
Override create/update/delete are **reality changes**. After the authoritative
mutation commits, the use case invokes a bounded post-commit impact check
(`ScheduleImpactService`):

- Window bound: the mutation's effective occurrences ∩ **[today−7, today+14]**
  (local). Outside-window changes trigger nothing (no global recalculation).
- Impact test: any **accepted auto-sourced placement**
  (`source ∈ {draft, reschedule, quick_capture}`) inside the window now
  overlaps an effective landscape occurrence → per-user
  `schedule_states.needs_review = true` with machine reasons and `impacted_at`.
- The check runs **after commit** and is failure-isolated: a mutation never
  fails because impact detection or notification failed.
- No proposal is auto-generated and nothing is auto-applied. The review state
  surfaces in read payloads (`schedule_needs_review` on Today/Week/Calendar/
  Month payloads) and is cleared by: successful draft/reschedule **apply**
  (version bump = reviewed), or a subsequent sync finding `no_changes`
  (explicit acknowledgement path below).

### 2.4 Run lock / concurrency — ADOPTED (cache lock, per-user scope)

The scheduling **authority scope is the user** (draft/reschedule/schedule
version are user-wide; confirmed by implementation). Lock identity therefore
derives from that authority:

- `schedule:sync:{userId}` — guards Sync Now computation.
- `schedule:weekly:{userId}` — guards weekly preparation per user.
- Acquired via `Cache::lock(...)` (database lock store — same facility as the
  AI generate throttle), TTL 60 s, non-blocking acquisition.
- `withoutOverlapping()` guards the console entry; the per-user locks prevent
  weekly-vs-Sync-Now and Sync-vs-Sync races for the same scope.
- Lock contention is deterministic: Sync Now returns `run_in_progress`
  (HTTP 200 with the state; the UI says "another sync is running"); the weekly
  job skips the user for that pass (next day's pass retries).
- **Apply is NOT lock-guarded**: it retains the existing optimistic
  `base_version`/`schedule_version` 409 semantics as the single concurrency
  authority for writes. Redis is NOT introduced.

### 2.5 Draft / proposal lifecycle — ADOPTED (minimal state model)

Persisted only for **weekly** drafts (`schedule_drafts`); manual drafts remain
ephemeral. Canonical states:

| State        | Meaning / transition                                                  |
|--------------|-----------------------------------------------------------------------|
| `pending`    | Generated, awaiting user review (weekly draft).                      |
| `applied`    | Apply succeeded (existing apply flow; server marks the draft).       |
| `discarded`  | User dismissed it (`POST /schedule/drafts/{id}/discard`).            |
| `superseded` | Replaced by a newer weekly draft (stale replacement / housekeeping).  |

- **Staleness** is derived, not stored: a `pending` draft is stale when
  `base_version` ≠ current schedule version, or reality changed after
  generation (`impacted_at > generated_at`). The API exposes the computed
  `stale` flag; the UI labels it and offers regenerate (= discard + new sync).
- Applying a stale draft goes through the **existing** conflict semantics:
  `base_version` mismatch → **409** (no silent rebase). The client then runs
  Sync Now to get a fresh proposal.
- Apply revalidation is unchanged (locked conflict 422, version 409, rollback).
- Cancel/discard never mutates accepted placements.

### 2.6 Apply authority — UNCHANGED (invariant)

Kinevo may calculate and may prepare drafts; it **must not apply** movement of
accepted flexible work without explicit user approval. Weekly drafts, Sync Now,
and reality-change detection all terminate in human review. AI has no path into
scheduling authority (unchanged).

### 2.7 Workspace scope — CONFIRMED (locked target semantics preserved)

Today, Week, Month are cross-workspace; Hard Landscape is global user reality;
flexible tasks retain workspace context. The weekly draft and Sync Now operate
on the **user-wide** scheduling authority and MUST NOT depend on the active
browser workspace. No workspace parameter enters the scheduling pipeline.

### 2.8 Failure behavior

- Weekly job: per-user isolation, `scheduler_runs` telemetry (success/failure),
  safe retry next pass; never partial state (draft insert is a single row).
- Sync Now: read-only computation; any failure is a standard error response;
  no partial writes exist to leak.
- Impact detection: post-commit, failure-isolated (logged, never breaks the
  authoritative mutation).
- Apply: existing transaction + rollback + idempotent retry semantics (ADR-015).

### 2.9 Notification / attention semantics — ADOPTED (DB channel only)

Two new notification types in the existing DB-poll channel (no push/email):

- `weekly_draft_ready` — "Your weekly plan draft is ready." Dedup:
  `(user_id, type, scheduled_for=<week anchor>)` (existing unique index →
  exactly one per user per week).
- `schedule_needs_review` — "Your schedule may be impacted by a change."
  Created only on the **false→true transition** of `needs_review` and deduped
  per local day via `scheduled_for` (max one per day; no unbounded duplicates).

Both are dismissed by reading the state (existing mark-read flow); no new
delivery infrastructure.

### 2.10 Sacred Anchor — KEEP, minimal producer implemented (Option A, bounded)

The domain concept is complete and tested (rule: 25 min, ≥ 06:00, first
qualifying slot, locked placement) but had **no producer** (controller
hard-coded `isSacredAnchor: false`). Decision: **keep** the concept and
implement the minimal scheduling-side producer:

- `tasks.is_sacred_anchor` (boolean, default false). A user has **at most one**
  sacred anchor task (validated on create/update).
- Draft/reschedule assembly passes the anchor task to the generator's
  `sacredAnchor` input (placed first) and marks the candidate
  `isSacredAnchor=true`; the anchor placement is locked-immutable like any
  locked placement.
- **Semantic relationship (supersession note):** *locked placement* is the
  general protection primitive; *Sacred Anchor* is a protected daily study
  commitment that inherits locked semantics and adds the 25-min/≥06:00/
  first-slot ordering rules. They are **not** redundant: an anchor is not
  auto-created by locking, and locking does not grant anchor ordering.
- **Deferred (out of scope):** FR-04 multi-track anchors, XP, study modes.
  Only the scheduling producer lands here; the remaining FR-04 surface stays a
  registered follow-up (TASK.md) — the unreachable invariant is closed at the
  scheduling layer.

### 2.11 Queue — NOT adopted

Synchronous bounded computation suffices (per-user loops over ≤ 14-day windows;
draft generation is deterministic and fast). The existing database queue and
worker container remain idle infrastructure for future needs. Introducing jobs
now would add delivery-semantics complexity with no current requirement.

### 2.12 `scheduler_runs` — EXTENDED (telemetry, not a lock)

Weekly prepare and Sync Now record runs (job names `schedule:prepare-weekly`,
`schedule:sync`) for observability, consistent with `eod:reconcile`. The table
gains no lock semantics (locks live in the cache lock store, §2.4).

## 3. Consequences

- **+** FR-27/FR-29 trigger model is real: weekly draft preparation, manual
  Sync Now, review-needed state — all without silent mutation.
- **+** One canonical rescheduling computation (`DynamicRescheduler`) serves
  manual reschedule and Sync Now; the Effective Landscape (ADR-015) is the
  single reality input.
- **+** Draft review becomes recoverable across sessions (persisted weekly
  draft) without a workflow framework.
- **−** Two new tables (`schedule_drafts`, `schedule_states`) and one task
  column (`is_sacred_anchor`) — additive migrations only.
- **−** Draft payloads are stored as JSON; generator output evolution must
  stay backward-compatible with stored pending drafts (stale detection bounds
  the risk).
- **Risks controlled:** duplicate weekly calculation (unique anchor + lock),
  concurrent Sync Now (lock), weekly-vs-sync race (same lock family), stale
  apply (409), locked work (immutable), notification spam (transition + day
  dedup), cross-workspace drift (user-wide authority asserted in tests).

## 4. Out of scope (explicit)

Offline reconciliation, remaining P28 UX, FrankenPHP, identity/email,
third-party runtime integrations, SRS v3 convergence, FR-04 XP/study-modes/
multi-track, push notifications, Redis.
