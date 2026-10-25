---
id: T-107
title: Implement task create, read, list, update and delete with the port implementations
milestone: M2
kind: feature
status: todo
depends_on: [T-106, T-103, T-104, T-101, T-017, T-019, T-014]
requirements: [TASK-FR-01, TASK-FR-02, TASK-FR-03, TASK-FR-07, TASK-FR-08, TASK-FR-10, SEC-AC-13, SEC-AC-14, SEC-AC-15, API-14]
branch: m2-t107-tasks-crud-endpoints
budget_lines: 400
assignee: ""
---

# T-107: Implement task create, read, list, update and delete with the port implementations

## Goal

Tasks can be created, listed with filters and search, read with subtasks, edited with optimistic locking and deleted; the tasks module implements the ports other modules consume.

## Context

> **Endpoints** (`spec/modules/tasks.md` §2): `GET /tasks?workspace_id=&goal_id=&milestone_id=&status=&due_before=&title_contains=&unanchored=&sort=` (`status` repeatable; sort `due_at`, `priority_tier`, `created_at`, `title`; `sort_invalid` with `details.allowed`); `POST /tasks {title, workspace_id?, goal_id?, milestone_id?, notes?, priority_tier?, estimate_minutes?, due_at?, is_anchor?}` (201; `task_goal_workspace_mismatch`, `task_milestone_goal_mismatch`, `milestone_requires_goal`, `anchor_exists`, `workspace_archived`, validation); `GET /tasks/{id}` (with `subtasks[]` and `allowed_transitions`); `PATCH /tasks/{id}` (If-Match; same errors plus `version_conflict`); `DELETE /tasks/{id}` (If-Match; `task_has_running_session` 409).
> **TASK-FR-01** consistency; the workspace defaults to the goal's workspace, else the default workspace. **TASK-FR-02** defaults: `priority_tier = tasks.default_priority_tier`, `estimate_minutes` null, `due_at` null. **TASK-FR-03** anchor rule (use T-106). **TASK-FR-07**: editing `estimate_minutes`, `due_at`, `is_anchor`, `goal_id` or `status` of a task with a current assignment emits `TaskSchedulingInputsChanged` (the assignment lookup uses `ScheduleCommandPort::has_current_assignment`, a no-op returning false until M3). **TASK-FR-08**: deleting removes subtasks (cascade) and, via `ScheduleCommandPort::on_task_deleted` and `SessionGuardPort::running_session(task)` (no-op ports until M3/M4), assignments and sessions; it refuses while a session runs. **TASK-FR-10**: `title_contains` is a case-insensitive substring match with `%`, `_` and `\` escaped, max 100 chars.
> **Validation** (§6): `title` short text 1–`tasks.title_max_chars`; `notes` long text ≤ 20 000 plain; `priority_tier` int 1–3; `estimate_minutes` int 1–1440 or null; `due_at` instant 1970…2100 (past allowed).
> **Events** (§7): `TaskCreated`, `TaskUpdated`, `TaskSchedulingInputsChanged`, `TaskDeleted`.
> **Ports implemented here**: `ProgressReadPort` (goal task counts: `done = status = completed`, `total` = all tasks of the goal), `TaskDetachPort` (`detach_goal`, `detach_milestone` set columns null in the caller's transaction), `WorkspaceUsagePort` (task count), `GoalLookup` consumers. **Ports added** (`kinevo-ports`, with fakes and default no-op implementations wired in `kinevo-app` until their owners exist): `ScheduleCommandPort` (`has_current_assignment(tx, task)`, `supersede_assignment(tx, task)`, `on_task_deleted(tx, task)`), `SessionGuardPort` (`running_session(tx, task) -> Option<SessionId>`).
> **SEC-AC-13/14/15**: `title_contains=%` returns literal matches only; mass assignment of `status`, `completed_at`, `progress_pct`, `account_id` → 422 `unknown_field`; foreign task ids → 404.

## Scope

**In:**
- Repositories with the filter/sort query builder (parameters only), use cases, handlers for the five endpoints and DTOs with `allowed_transitions`.
- Implementations of `ProgressReadPort`, `TaskDetachPort`, `WorkspaceUsagePort`; the two new ports with fakes and no-op defaults; replace the `NoTasks` fakes wired in T-103.
- Cursor pagination for the list; OpenAPI regeneration.

**Out:**
- Status endpoint and subtasks: T-108.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/{schedule,session}.rs` | create | two ports with fakes and no-op defaults |
| `crates/kinevo-mod-tasks/src/{app,infra,http}/tasks.rs` | create | feature code |
| `crates/kinevo-mod-tasks/src/infra/query.rs` | create | filter and sort builder |
| `crates/kinevo-mod-tasks/tests/{tasks_api,search_escaping,ports}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-app/src/modules.rs` | modify | wire real task ports and no-op schedule/session ports |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub struct TaskFilter { pub workspace_id: Option<WorkspaceId>, pub goal_id: Option<GoalId>, pub milestone_id: Option<MilestoneId>, pub statuses: Vec<TaskStatus>,
                        pub due_before: Option<Instant>, pub title_contains: Option<ContainsText>, pub unanchored: bool, pub sort: TaskSort }
pub fn escape_like(s: &str) -> String;   // escapes %, _, \
```

## Business rules

1. `unanchored=true` returns tasks with null `goal_id`; it combines with other filters by AND.
2. `status` accepts a repeated parameter; unknown values are validation errors.
3. A create in an archived workspace is refused with `workspace_archived` via `WorkspaceAccess`.
4. The anchor rule runs in the use case with the DB constraint as a backstop; `anchor_exists` carries `details.task_id` of the existing anchor.
5. Delete refuses when `SessionGuardPort` reports a running session.

## Security

- The `title_contains` value is bound as a parameter after escaping; a test sends `%`, `_`, `\\` and SQL fragments.
- All requests DTOs use `deny_unknown_fields`.
- The query builder never concatenates user input.

## Implementation notes

- Keep the builder as small as possible: a fixed set of optional WHERE fragments with positional parameters.
- `due_before` compares `due_at < $n` (half-open).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a task with a goal in another workspace **When** created **Then** 422 `task_goal_workspace_mismatch` | `tasks_api::task_fr_01_goal_workspace_mismatch` |
| AC-2 | **Given** omitted fields **When** creating **Then** defaults from the policies apply | `tasks_api::task_fr_02_defaults_from_policy` |
| AC-3 | **Given** an existing open anchor **When** creating another anchor **Then** 409 `anchor_exists` with `details.task_id` | `tasks_api::task_fr_03_anchor_exists_with_id` |
| AC-4 | **Given** titles `100%` and `100 percent` **When** searching `%` **Then** only the literal match returns | `search_escaping::sec_ac_13_literal_percent` |
| AC-5 | **Given** `PATCH` with `status`, `completed_at`, `progress_pct` or `account_id` **When** sent **Then** 422 `unknown_field` | `tasks_api::sec_ac_14_mass_assignment` |
| AC-6 | **Given** another account's task id **When** read, patched or deleted **Then** 404 | `tasks_api::sec_ac_15_foreign_task_not_found` |
| AC-7 | **Given** a running session (fake guard) **When** deleting **Then** 409 `task_has_running_session` | `tasks_api::task_fr_08_delete_refused_while_running` |
| AC-8 | **Given** a task with a current assignment (fake) **When** its estimate changes **Then** `TaskSchedulingInputsChanged` is emitted | `tasks_api::task_fr_07_scheduling_inputs_event` |
| AC-9 | **Given** goals with tasks **When** `ProgressReadPort`, `detach_goal` and `WorkspaceUsagePort` are called **Then** counts and detach behave as specified | `ports::task_side_ports_behave` |

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
