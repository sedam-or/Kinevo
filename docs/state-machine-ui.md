# Kinevo — State-Machine UI Matrix

> **Document role:** Reference. Maps every backend state transition to the UX
> surface that presents it (rescue §84). This is the living record for
> design.md §84 "State Machine UI".
>
> **Status:** Baselines recorded during TASK-R3 (2026-08-21).

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
