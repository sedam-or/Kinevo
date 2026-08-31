# Kinevo — State-Machine UI Matrix

> **Document role:** Reference. Maps every backend state transition to the UX
> surface that presents it (rescue §84). This is the living record for
> design.md §84 "State Machine UI".
>
> **Status:** Entity baselines recorded during TASK-R3 (2026-08-21); the global
> user-facing state matrix (P28-011) and retention-critical failure matrix
> (RET-013) added during P28 closure (2026-08-31).
>
> **Artifacts:** P28-011 canonical state matrix + RET-013 failure matrix are
> recorded in the sections at the end of this document.

For every entity the matrix records, in order:

```text
State
→ Allowed action(s)
→ Primary button (design.md §2.3, §51)
→ Secondary actions
→ Visual state (VisualStateBadge, resources/js/visualstate/)
→ Confirmation when the mutation is material
```

Color is never the only signal. Every visual state carries a glyph and, for
transient states, a dashed border (design.md §5.2, §11).

---

## Task (TaskStatus)

Source of truth: `resources/js/task/types.ts` `TASK_TRANSITIONS`. Backend is the
state authority; the UI only presents valid transitions.

| State        | Primary action | Secondary actions                          | Visual state                     | Confirmation |
| ------------ | -------------- | ------------------------------------------ | -------------------------------- | ------------ |
| `backlog`    | Schedule       | Start, Complete, Skip                      | `draft`                          | —            |
| `scheduled`  | Start          | Miss, Conflict, Skip, Back to backlog      | `locked` when schedule-locked    | —            |
| `in_progress`| Complete       | Partial, Conflict, Skip                    | `syncing` while timer writes     | —            |
| `partial`    | Continue       | Complete, Schedule, Skip                   | `draft`/`syncing`                | —            |
| `continued`  | Schedule/Start | Complete, Skip                             | `draft`                          | —            |
| `missed`     | Reschedule     | Back to backlog, Complete                  | `overdue` (glyph ⏰)             | —            |
| `conflict`   | Reschedule     | Start, Back to backlog                     | `conflict` (glyph !, dashed)     | material      |
| `completed`  | — (terminal)   | —                                          | `saved`                          | —            |
| `skipped`    | — (terminal)   | —                                          | `saved`                          | —            |

Surface: `task/TaskListView.vue` (list transitions via `task-to-${next}`
buttons), `task/TaskDetailView.vue` (`task-actions`), `today/TodayView.vue`
(NOW card + `ExecutionTimer`). Primary action is emphasized as the KButton
`primary` variant on the workspace edit form.

---

## Goal (GoalStatus)

Source of truth: `goal/types.ts` `GOAL_STATUSES` (`draft, active, paused,
completed, archived, dropped`).

| State       | Primary action     | Visual state | Confirmation |
| ----------- | ------------------ | ------------ | ------------ |
| `draft`     | Activate           | `draft`      | —            |
| `active`    | Show progress      | `syncing`    | —            |
| `paused`    | Resume             | `proposed`   | —            |
| `completed` | — (terminal)       | `saved`      | —            |
| `archived`  | — (terminal)       | `neutral`    | —            |
| `dropped`   | — (terminal)       | `failed`     | material      |

Surface: `goal/GoalListView.vue`, `goal/GoalDetailView.vue`.

---

## Milestone (MilestoneStatus)

Source: `goal/types.ts` `MILESTONE_STATUSES` (`planned, active, blocked,
completed, dropped`).

| State      | Primary action | Visual state  |
| ---------- | -------------- | ------------- |
| `planned`  | Activate       | `draft`       |
| `active`   | Mark complete  | `syncing`     |
| `blocked`  | Unblock        | `conflict`    |
| `completed`| — (terminal)   | `saved`       |
| `dropped`  | — (terminal)   | `failed`      |

---

## Program (ProgramStatus)

Source: `goal/types.ts` `PROGRAM_STATUSES` (`active, paused, completed, dropped`).

| State       | Primary action | Visual state |
| ----------- | -------------- | ------------ |
| `active`    | Pause          | `syncing`    |
| `paused`    | Resume         | `proposed`   |
| `completed` | — (terminal)   | `saved`      |
| `dropped`   | — (terminal)   | `failed`     |

---

## Canvas / Note (document persistence)

Surface: `canvas/*`, `note/NoteEditView.vue`, `editor/EditorHost.vue`.

| Persistence state | Visual state | Where surfaced           |
| ----------------- | ------------ | ------------------------ |
| Editing (dirty)   | `syncing`    | editor autosave indicator |
| Saved             | `saved`      | `note-save-state` (top-right) |
| Offline queue     | `offline`    | `SyncStatusPanel`        |
| Sync retry        | `retrying`   | `SyncStatusPanel`        |
| Conflict          | `conflict`   | note error banner        |
| Failed            | `failed`     | note error banner        |

Note autosave is debounced (600ms) and surfaced live via `note-save-state`
(design.md §32). LinkManager (knowledge sidebar, design.md §33) is embedded in
the note edit surface.

---

## Schedule / AI Proposal

| Concept        | Visual state | Notes                                        |
| -------------- | ------------ | -------------------------------------------- |
| Schedule draft | `proposed`   | presented distinctly from committed data     |
| AI proposal    | `proposed`   | never rendered as committed; requires review / explicit approval |
| Hard landscape | `locked`     | LOCK glyph on timeline blocks                |
| Sync state     | `online/offline/syncing/retrying/failed` | `SyncStatusPanel` |

---

## Owners

When a surface changes the allowed transitions for an entity, update this matrix
and the corresponding `types.ts` `*_TRANSITIONS` / `*_STATUSES` constants in the
same change. Never render an action the backend does not accept (design.md §19,
AGENTS.md API rule).

---

## Global user-facing state matrix (P28-011)

The entity matrices above describe backend transitions. This section records what
the **user actually sees** for each surface in the states that matter in product
terms — LOADING, EMPTY, SUCCESS, PARTIAL, OFFLINE, QUEUED, SYNCING, STALE,
NEEDS_REVIEW, CONFLICT, FAILED, UNAVAILABLE, ENTITLEMENT_BLOCKED. N/A is explicit
rather than fabricated UX. For each meaningful cell the contract answers: what
the user sees, what they can do, whether data is preserved, the canonical action,
and the accessibility semantics. Canonical loading indicator: muted `Loading…`
text (`data-testid="{surface}-loading"`); canonical failure: `InlineError`
(`components/InlineError.vue`) with the message + a `Try again` recovery path
that re-runs the surface's own load action.

Legend — ✓ implemented · ~ partial · N/A not applicable (state cannot occur on
this surface) · – not surfaced.

| Surface | LOADING | EMPTY | SUCCESS | PARTIAL | OFFLINE | QUEUED/SYNCING | STALE | NEEDS_REVIEW | CONFLICT | FAILED | UNAVAILABLE | ENTITLEMENT_BLOCKED |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| Today (`today/TodayView.vue`) | ✓ `today-loading` | ✓ first-session guide (RET-006) + no-now block | ✓ NOW→NEXT→Timeline→context | ~ no events but hard landscape/empty slots | ✓ app-wide sync state; API errors offline-aware | ✓ SyncStatusPanel (`sync-state`) | ~ no stale indicator (see `offline/today-cache.ts` — not wired) | ✓ `today-needs-review` banner | ~ conflict badges on events (`visualstate/derive`) | ✓ `InlineError` + retry | N/A | N/A |
| Week (`week/WeekView.vue`) | ✓ | ~ | ✓ | ~ | ✓ app-wide | ✓ app-wide | – | ✓ surfaces schedule needs review | – | ✓ | N/A | N/A |
| Goals (`goal/GoalListView.vue`) | ✓ `goals-loading` | ✓ `goal-empty` (4-question copy + FeatureHelp) | ✓ | N/A | ✓ app-wide | N/A | – | ✓ `goal-proposal-ready` | – | ✓ `InlineError` + retry | ~ `AiNotConfiguredNotice` gates breakdown | ✓ UpgradeNotice on `ENTITLEMENT_LIMIT` |
| Goal detail (`goal/GoalDetailView.vue`) | ✓ | ~ | ✓ | ✓ partial milestones | ✓ app-wide | N/A | – | ✓ proposal review | – | ✓ | ✓ | ✓ |
| Tasks (`task/TaskListView.vue`) | ✓ `task-loading` | ✓ `task-empty` + FeatureHelp | ✓ | ~ filter | ✓ app-wide | N/A | – | – | ✓ `conflict` status badge | ✓ `InlineError` + retry | N/A | ✓ |
| Knowledge/Notes (`note/NotesListView.vue`) | ✓ `notes-loading` | ✓ `note-empty` (RET-002 copy) | ✓ | ✓ search results | ✓ app-wide | ✓ note autosave state | ✓ note conflict banner (`NoteEditView`) | N/A | ✓ `This note was changed elsewhere` + reload | ✓ `InlineError` + retry | ~ AI summarize gated | N/A |
| Canvas (`canvas/CanvasListView.vue`) | ✓ `canvas-loading` | ✓ `canvas-empty` (RET-002) | ✓ | N/A | ✓ app-wide + `NextActionBanner` view-sync | ✓ canvas save state | ✓ autosave stale→conflict | N/A | ✓ `CanvasWorkspaceView` conflict banner + reload server copy | ✓ `InlineError` + retry; CanvasHost editor-failure retry/read-only | ✓ `AiNotConfiguredNotice` | N/A |
| Schedule draft / Reschedule (`schedulerdraft/RescheduleView.vue`) | ✓ | ~ | ✓ | ✓ | ✓ app-wide | ✓ weekly draft pending | ✓ stale draft refreshed in place (ADR-016) | ✓ `weekly-draft-banner` + apply | – | ✓ | ~ AI not involved | N/A |
| Import (KRS) (`imports/`) | ✓ | ✓ | ✓ | ✓ partial import surfacing | ✓ app-wide | N/A | – | ✓ preview/edit before confirm | – | ✓ failed import message + retry | N/A | N/A |
| Analytics/Progress (`analytics/AnalyticsView.vue`) | ✓ `analytics-loading` | ✓ `analytics-empty` + FeatureHelp | ✓ | ~ pillar filters | ✓ app-wide | N/A | – | N/A | N/A | ✓ `InlineError` + retry | N/A | ✓ plan gating for history |
| Workspace (`workspace/WorkspaceHome.vue`, `WorkspaceManager.vue`) | ✓ | N/A (default workspace auto-provisioned) | ✓ | N/A | ✓ app-wide | N/A | – | N/A | N/A | ✓ | N/A | ✓ `UpgradeNotice` on limit |
| AI proposal (`ai/ProposalReviewCard.vue`) | ✓ proposal load | N/A | ✓ review/edit/accept | ✓ | ✓ app-wide | ✓ pending proposal | – | ✓ pending→review | – | ✓ proposal error | ✓ `AiNotConfiguredNotice` routes to Settings | ✓ |

Cross-cutting rules:
- OFFLINE is app-wide (`api.setOnline()` in `auth/AuthHost.vue`, visible in
  `shell/SyncStatusPanel.vue`); per-surface reads surface as an offline-aware
  `ApiError`. Server stays canonical — IndexedDB is cache/queue only (ADR-017).
- STALE today data: `offline/today-cache.ts:isStale()` exists but is not wired
  into a view — documented as the remaining high-value gap (not a blocker: the
  schedule is re-derived from the server on every reload).
- No surface ever hides a meaningful state to reach a lower-effort UI; N/A is
  declared explicitly.

---

## Retention-critical failure matrix (RET-013)

Every failure a new user can hit must answer: what happened (user-visible
explanation), is my data preserved, how do I recover, what are the retry
semantics, and is there an escape route.

| Failure | User sees | Data preserved | Recovery | Retry | Escape |
|---|---|---|---|---|---|
| AI unavailable | `AiNotConfiguredNotice` (routes to Settings) | Yes | Configure/re-enable a provider | Test connection (`ai-section-status`) | Dismiss; continue without AI |
| AI provider misconfigured | Settings status chip `unavailable` / `not configured` + error code | Yes | Fix provider/base-url/key in Settings | `Test connection` | Disable AI |
| Network offline | App-wide sync state + offline-aware API error | Yes (queued) | Reconnect | SyncStatusPanel `Retry` | Continue offline |
| Offline queued mutation | Sync queued count + `NextActionBanner` view-sync | Yes (ledger `offline_operations`) | Reconnect → drain | SyncStatusPanel | Keep working |
| Offline conflict | SyncStatusPanel `conflict` + `Discard local change` | Server copy preserved | Review → accept server or discard | SyncStatusPanel | Dismiss |
| Stale schedule proposal | Draft remains pending; refreshed in place | Yes | Re-review | `weekly-draft-apply` | Dismiss banner |
| Schedule needs review | `today-needs-review` banner + Schedule surface | Yes | Open Schedule → `Sync Now` → review impact | Re-run sync | Dismiss |
| No available scheduling slot | Empty timeline + capacity feedback | Yes | Adjust capacity/reality or reschedule | `Sync Now` | Move on |
| Failed import (KRS) | Import error message with details | Source file kept by user | Re-upload | Retry import | Cancel import |
| Partial import | Per-row preview/edit before confirm | Confirmed rows only | Edit/re-import rejected rows | Re-import | Confirm subset |
| Empty first session | `first-session-guide` (RET-006) | Yes | Quick capture or create a goal | — | Close/ignore |
| Server failure (5xx) | `InlineError` + `Try again` on the surface | Yes (server) | Wait + retry | `Try again` | Navigate elsewhere |

Canonical implementation notes: `InlineError.vue` is the uniform failure control;
`AiNotConfiguredNotice` is the uniform AI-gate; SyncStatusPanel is the uniform
offline/conflict surface. Never reveal note/task content or provider secrets in
failure text (AGENTS.md AI rule, privacy contract).
