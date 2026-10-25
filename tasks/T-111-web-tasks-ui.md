---
id: T-111
title: Build the Tasks list and the task editor with subtasks and status actions
milestone: M2
kind: feature
status: todo
depends_on: [T-110, T-107, T-108]
requirements: [TASK-FR-01, TASK-FR-03, TASK-FR-04, TASK-FR-10, FE-04, FE-14, UXS-02, CA-15, DS-A11Y-13]
branch: m2-t111-web-tasks-ui
budget_lines: 400
assignee: ""
---

# T-111: Build the Tasks list and the task editor with subtasks and status actions

## Goal

Users can find, create, edit and complete tasks with subtasks and an anchor flag; all status actions come from the server, and conflicts never discard the user's text.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/plan/tasks` (list with filters); task create/edit in a side sheet or dialog opened from the list and from the goal detail. Descriptor nav: `tasks` gains the nav item `tasks` (`/plan/tasks`, permission `tasks.task.read`).
> **List** (`modules/tasks.md` §2): filters `workspace_id`, `goal_id`, `milestone_id`, `status` (repeatable), `due_before`, `title_contains` (≤ 100 chars), `unanchored`, `sort` (`due_at`, `priority_tier`, `created_at`, `title`); states `tasks.empty` (Clear filters), loading, error, stale; filters live in URL search params.
> **Editor**: title, notes (plain text), workspace, goal (filtered by workspace), milestone (filtered by goal), priority tier 1–3 (labels from message keys), estimate minutes, due date/time, anchor switch (`task.anchor_exists` inline with Open it), subtask list with add/edit/complete/delete and Move up/down. Errors: `task_goal_workspace_mismatch`, `task_milestone_goal_mismatch`, `milestone_requires_goal`, `workspace_archived` (banner `workspace.archived.readonly`), `subtask_limit_reached`, `version_conflict` → `conflict.task` card with Reload and Reapply my change (FE-14: text is kept).
> **CA-15 / SM-T2**: status actions (Complete, Skip, Move to backlog, Reopen, Continue) render only from `allowed_transitions`; the web has no transition table. Progress is displayed from `progress_pct` (server value) as a labelled bar with the number.
> **DS-A11Y-13**: status uses icon plus text via `StatusPill`, never colour alone. **Time (FE-06)**: due dates display in the account timezone; the client never computes "today" from its clock for decisions.
> **Mutations** send `Idempotency-Key`; versioned updates send `If-Match`; status changes send `base_version`.

## Scope

**In:**
- Descriptor edit in Rust: `tasks` nav item (+ descriptor test).
- `features/tasks/`: list page, filter bar, row actions, `TaskSheet` editor, subtasks component, status actions, hooks and zod schemas, MSW handlers, tests, axe spec.
- `StatusPill` and `PriorityBadge` components in `components/`.

**Out:**
- Scheduling controls and Today: M3/M4. Bulk operations: not in 1.0 (TASK-FR-09).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-tasks/src/lib.rs, tests/descriptor.rs` | modify | nav item |
| `web/src/features/tasks/{TasksPage,FilterBar,TaskRow,TaskSheet,SubtaskList,StatusActions}.tsx` | create |  |
| `web/src/features/tasks/hooks.ts, schemas.ts` | create |  |
| `web/src/components/{StatusPill,PriorityBadge}.tsx` | create |  |
| `web/src/features/tasks/*.test.tsx` | create | tests |
| `web/tests/e2e/tasks.a11y.spec.ts` | create | axe |

## Interfaces

```ts
useTasks(filter); useTask(id); useTaskMutations(); useChangeStatus(): mutate({ id, to, baseVersion })
```

## Business rules

1. The status menu offers exactly `allowed_transitions`; if the list is empty no menu renders.
2. Choosing a goal resets the milestone if it no longer belongs to the goal.
3. Anchor creation failures link to the existing anchor by `details.task_id`.
4. Search input is debounced and sends `title_contains` unescaped (the server escapes).

## Security

- Titles and notes render as text.
- No task content is logged or sent to error reports.

## Implementation notes

- The estimate field accepts minutes only; helper text formats hours with `Intl`.
- Keep editor state in the sheet so closing with unsaved text asks for confirmation (alertdialog).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** filters in the URL **When** the page loads **Then** the request carries them and the bar shows them | `tasks_ui::filters_roundtrip_url` |
| AC-2 | **Given** no match **When** rendered **Then** `tasks.empty` with Clear filters | `tasks_ui::tasks_empty_clear_filters` |
| AC-3 | **Given** a task with `allowed_transitions=[completed, skipped]` **When** rendered **Then** exactly those actions show | `tasks_ui::sm_t2_actions_from_allowed_transitions` |
| AC-4 | **Given** a `version_conflict` on save **When** it occurs **Then** the conflict card shows and the typed text remains | `tasks_ui::fe_14_conflict_keeps_text` |
| AC-5 | **Given** `anchor_exists` **When** saving an anchor **Then** `task.anchor_exists` shows with an Open it link | `tasks_ui::task_fr_03_anchor_exists_message` |
| AC-6 | **Given** a goal change **When** the milestone belongs to the old goal **Then** the milestone resets | `tasks_ui::goal_change_resets_milestone` |
| AC-7 | **Given** `title_contains` text with `%` **When** typed **Then** it is sent as typed and results are literal (via MSW assertion) | `tasks_ui::task_fr_10_search_sent_unescaped` |
| AC-8 | **Given** the list and sheet **When** scanned by axe in grayscale review mode **Then** zero serious/critical violations and statuses carry text | `tasks.a11y::tst_11_tasks_axe_clean_status_not_color_only` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-workspaces -p kinevo-mod-goals -p kinevo-mod-tasks --test descriptor
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
