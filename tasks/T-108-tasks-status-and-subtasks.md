---
id: T-108
title: Implement the status endpoint and the subtask endpoints
milestone: M2
kind: feature
status: todo
depends_on: [T-107]
requirements: [TASK-FR-04, TASK-FR-05, TASK-FR-06, D-36, SEC-AC-14, SEC-AC-15]
branch: m2-t108-tasks-status-and-subtasks
budget_lines: 370
assignee: ""
---

# T-108: Implement the status endpoint and the subtask endpoints

## Goal

Users can change a task's status within the machine, add and edit subtasks, and the derived progress stays consistent in the same transaction.

## Context

> **Endpoints** (`spec/modules/tasks.md` §2): `POST /tasks/{id}/status {to, base_version, reason?}` (200; `invalid_transition` 409 with `details.from/to`, `version_conflict`, `anchor_exists` on reopen); `POST /tasks/{id}/subtasks {title, position?}` (201; the task `version` increments because progress changes; `subtask_limit_reached` with `details.max` at `tasks.max_subtasks`); `PATCH /subtasks/{id}` (If-Match; `title?`, `completed?`, `position?`); `DELETE /subtasks/{id}` (If-Match).
> **Data flow** (§5): `UpdateTaskStatus`, one transaction: load the task `FOR UPDATE` → check `base_version` → domain `transition(to, Actor::User, now)` → persist (status, `completed_at`, `progress_pct`, `version`) → if leaving `scheduled`/`backlog` with an assignment, call `ScheduleCommandPort::supersede_assignment(task)` in the same transaction → append outbox `TaskStatusChanged` (and `TaskCompleted` / `TaskReopened`). Subscribers run after commit. A failing guard changes nothing and the response carries the current state and `version`.
> **TASK-FR-04**: the user may request only ✓U targets. **TASK-FR-05**: `progress_pct` recomputed in the same transaction as each subtask or status change. **TASK-FR-06**: completing with open subtasks marks none. **D-36**: reopen clears `completed_at`, never deletes history, emits `TaskReopened`. **TASK-FR-07**: status changes of a task with a current assignment emit `TaskSchedulingInputsChanged`.
> **Validation**: `subtask.title` short text 1–500, max `tasks.max_subtasks` per task. **Events**: `TaskStatusChanged`, `TaskCompleted`, `TaskReopened`. The goals module reads counts on demand (no subscriber needed).
> **SEC-AC-14/15**: mass assignment and foreign ids as before.

## Scope

**In:**
- Use cases `UpdateTaskStatus`, `AddSubtask`, `UpdateSubtask`, `DeleteSubtask`; handlers; DTOs; the `allowed_transitions` in task and subtask-bearing responses; OpenAPI regeneration.

**Out:**
- System-actor transitions (scheduler, execution, job): M3/M4 tasks call the same domain function with their actor.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-tasks/src/{app,infra,http}/status.rs, subtasks.rs` | create |  |
| `crates/kinevo-mod-tasks/tests/{status_api,subtasks_api}.rs` | create | AC-1…AC-8 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub struct StatusChange { pub to: TaskStatus, pub base_version: i64, pub reason: Option<ShortText> }
// POST /tasks/{id}/status → 200 Task (with allowed_transitions) | 409 invalid_transition { from, to } | 409 version_conflict
```

## Business rules

1. The user can only request ✓U targets; `scheduled` or `in_progress` targets from the user are `invalid_transition`.
2. Reopening an anchor re-checks `anchor_exists`.
3. Adding or deleting a subtask recomputes `progress_pct` and bumps the task `version`.
4. Same-state status requests return 200 with the version unchanged and emit nothing.

## Security

- `base_version` mismatch returns 409 without side effects.
- `reason` is short text, stored only if the schema keeps it (not persisted in 1.0; accepted and ignored for forward compatibility, documented in the DTO).

## Implementation notes

- `reason` has no column in the DDL; do not add one. The field is accepted for the scheduler/execution flows that will persist it in their own tables.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a backlog task **When** `to=completed` **Then** `completed_at` is set from the injected clock, `progress_pct = 100`, events emitted | `status_api::task_fr_06_complete_sets_completed_at` |
| AC-2 | **Given** a task with open subtasks **When** completed **Then** no subtask changes | `status_api::task_fr_06_completion_leaves_subtasks` |
| AC-3 | **Given** completed **When** reopened **Then** backlog, `completed_at` null, `TaskReopened` emitted | `status_api::d_36_reopen_flow` |
| AC-4 | **Given** a user request to `in_progress` **When** sent **Then** 409 `invalid_transition` with `details` | `status_api::task_fr_04_user_cannot_start` |
| AC-5 | **Given** a completed anchor and another open anchor **When** reopening **Then** 409 `anchor_exists` | `status_api::reopen_respects_anchor_rule` |
| AC-6 | **Given** 3 subtasks with 1 done **When** one is added or completed **Then** `progress_pct` follows the formula in the same transaction and the task version increments | `subtasks_api::task_fr_05_progress_recomputed_in_tx` |
| AC-7 | **Given** `tasks.max_subtasks` reached **When** adding **Then** 409 `subtask_limit_reached` with `details.max` | `subtasks_api::subtask_limit_reached` |
| AC-8 | **Given** a stale `base_version` **When** changing status **Then** 409 `version_conflict` and nothing changes | `status_api::status_version_conflict` |

## Tests

```
cargo nextest run -p kinevo-mod-tasks --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
