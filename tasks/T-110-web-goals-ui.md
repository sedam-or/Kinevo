---
id: T-110
title: Build the Goals list, goal detail and milestone management screens
milestone: M2
kind: feature
status: todo
depends_on: [T-109, T-103, T-104]
requirements: [GOAL-FR-02, GOAL-FR-03, GOAL-FR-05, GOAL-FR-06, GOAL-FR-08, FE-01, FE-14, UXS-02, UXS-04, CA-15]
branch: m2-t110-web-goals-ui
budget_lines: 400
assignee: ""
---

# T-110: Build the Goals list, goal detail and milestone management screens

## Goal

Users can create and manage goals and their milestones, change status from the server-provided transitions, reorder milestones by buttons, and delete a goal choosing what happens to its tasks.

## Context

> **Routes** (`spec/08-frontend.md` §3): `/plan/goals` (list) and `/plan/goals/$goalId` (detail with milestones and tasks); module `goals`. Descriptor nav: the `goals` module gains nav item `goals` (`/plan/goals`, permission `goals.goal.read`); the `tasks` module gains `tasks` (`/plan/tasks`, permission `tasks.task.read`) in T-111.
> **Screen contract** (§5): Goals — list with progress facts, detail with milestones/tasks, primary action New goal; states: empty (`goals.empty`), error, conflict (`conflict.task`-style card for goals). Progress shows `milestones_done/milestones_total` and `tasks_done/tasks_total` from `GET /goals/{id}` as numbers and a labelled ProgressBar (never colour-only).
> **CA-15 / SM-T2**: status actions render only from `allowed_transitions`; the web never hard-codes goal rules. Setting `achieved` with open milestones shows a warning in the confirm step (GOAL-FR-03). After `GoalMilestonesAllDone` the UI suggests "mark goal achieved" (a banner shown when all milestones are done and the goal is active; nothing automatic, GOAL-FR-05).
> **Delete** (`ux-states.md` §5): `goal.delete.confirm` alertdialog with the goal title and task count; `goal.has_tasks` modal when the server answers `goal_has_tasks` (choices: Delete goal and keep the tasks → retry with `detach_tasks=true`, or Cancel).
> **Milestones**: add, edit, mark done/open, delete, reorder with Move up/Move down buttons (no drag and drop, DS-A11Y-04) calling the reorder endpoint with the full id list; date rule error `milestone_after_goal` inline.
> **Forms** (FE-04): zod mirrors `goals.title_max_chars` read from `GET /policies`? No: the title bound is not exposed to non-admin users; forms show the server's `validation_failed` field error (client keeps only the structural rule non-empty). Dates use the account timezone and `date` kind (1970…2100).
> **GOAL-FR-08**: the list hides closed goals by default and offers a status filter that includes them.

## Scope

**In:**
- Descriptor edit in Rust: `goals` nav item (+ descriptor test), `tasks` nav item belongs to T-111.
- `features/goals/`: list page with workspace picker and status/sort controls, create/edit dialog, detail page with milestone list, status menu, delete flows, achieved-suggestion banner.
- MSW handlers from OpenAPI examples; component tests; a11y spec additions.

**Out:**
- Task list and task editing: T-111. AI breakdown entry: M6.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-goals/src/lib.rs, tests/descriptor.rs` | modify | nav item |
| `web/src/features/goals/{GoalsPage,GoalDetailPage,GoalForm,MilestoneList,MilestoneForm,GoalStatusMenu,DeleteGoalDialog}.tsx` | create |  |
| `web/src/features/goals/hooks.ts, schemas.ts` | create |  |
| `web/src/features/goals/*.test.tsx` | create | tests |
| `web/tests/e2e/goals.a11y.spec.ts` | create | axe |

## Interfaces

```ts
useGoals({ workspaceId, status, sort }); useGoal(id); useGoalMutations(); useMilestones(goalId); useReorderMilestones(goalId)
```

## Business rules

1. Status buttons are generated from `allowed_transitions`; an unknown future status renders as plain text without actions.
2. Reorder sends the complete id list; a `reorder_set_mismatch` reloads the list.
3. Optimistic updates for milestone done/open roll back on error; conflicts show the conflict card with Reload.
4. Closed goals are excluded from the default list and clearly labelled when shown.

## Security

- Goal and milestone text renders as text.
- Deleting a goal is a two-step confirmation naming the goal.

## Implementation notes

- For `goals.title_max_chars`, the form relies on the server error; do not copy the bound into the client (no business literals).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** no goals **When** the list renders **Then** `goals.empty` with a New goal action | `goals_ui::goals_empty_state` |
| AC-2 | **Given** a goal with `allowed_transitions` **When** rendered **Then** only those actions appear | `goals_ui::sm_t2_actions_from_allowed_transitions` |
| AC-3 | **Given** achieving with open milestones **When** confirming **Then** a warning shows and the request succeeds | `goals_ui::goal_fr_03_achieve_with_open_milestones_warns` |
| AC-4 | **Given** a goal with tasks **When** deleting **Then** `goal.has_tasks` offers keep-tasks and retries with `detach_tasks=true` | `goals_ui::goal_fr_06_delete_keep_tasks_flow` |
| AC-5 | **Given** Move up on the second milestone **When** pressed **Then** the full id list is sent in the new order and focus stays on the moved item | `goals_ui::reorder_buttons_send_full_list` |
| AC-6 | **Given** all milestones done on an active goal **When** rendered **Then** the achieved suggestion banner shows and nothing is changed automatically | `goals_ui::goal_fr_05_suggest_achieved_only` |
| AC-7 | **Given** `milestone_after_goal` **When** saving **Then** the inline date error shows | `goals_ui::goal_fr_02_milestone_date_error` |
| AC-8 | **Given** the screens **When** scanned by axe **Then** zero serious/critical violations | `goals.a11y::tst_11_goals_axe_clean` |

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
