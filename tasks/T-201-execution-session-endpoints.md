---
id: T-201
title: Implement the focus-session endpoints with atomic task transitions
milestone: M4
kind: feature
status: todo
depends_on: [T-200]
requirements: [EXE-FR-04, EXE-FR-05, EXE-FR-06, EXE-FR-07, SEC-AC-21, SEC-AC-22, SEC-AC-23, TST-19, CA-10]
branch: m4-t201-execution-session-endpoints
budget_lines: 400
assignee: ""
---

# T-201: Implement the focus-session endpoints with atomic task transitions

## Goal

Users can start, complete and abandon focus sessions; each step changes the session and the task together or not at all.

## Context

> **Endpoints** (`spec/modules/execution.md` §2): `POST /focus-sessions {task_id, assignment_id?, target_minutes?}` + `Idempotency-Key` (201; `session_already_running` 409 with `details.session_id`,`details.task_id`; `task_not_startable` 409 with `details.status`; `not_found`); `GET /focus-sessions/current` (200 or 204, with `stale` flag); `POST /focus-sessions/{id}/complete {outcome: done|partial, note?, ended_at?, base_version}` (200 `{session, task}`; `session_not_running`, `ended_at_invalid` 422, `version_conflict`); `POST /focus-sessions/{id}/abandon {base_version}`; `GET /focus-sessions?task_id=&from=&to=` (page, newest first).
> **Start** (EXE-FR-04): one transaction — take the account's running slot (the partial unique index), create the session `running` with `target_minutes` default `execution.focus.default_minutes`, transition the task to `in_progress` through `TaskCommandPort::transition(tx, task, to, Actor::Execution)` (allowed from `backlog`, `scheduled`, `continued`, `conflict`; others → `task_not_startable`). **Complete** (§6): BEGIN → `SELECT … FOR UPDATE` session → check `base_version`/state → domain complete → `UPDATE` → `transition(task, completed|partial, Actor::Execution)` in the same `Tx` → outbox `SessionCompleted` (payload carries `task_id`, `workspace_id`, `goal_id`, `minutes = floor((ended_at − started_at)/60 s)`, `started_at`, `ended_at`), plus the task events the tasks module emits → COMMIT. **Abandon** (EXE-FR-06): session `abandoned`; the task returns to `scheduled` if it has a current assignment (`ScheduleCommandPort::has_current_assignment`), else `backlog`; no progress event.
> **Ports**: extend `TaskCommandPort` (T-160) with `transition(tx, task, to: TaskStatus, actor: Actor) -> Result<TaskSnapshot, AppError>` implemented by `tasks` using its domain function (the actor argument is constructible by execution/scheduler through a ports-level token, as T-106 notes); implement `SessionGuardPort::{running_session, running_slots}` (introduced in T-107/T-159) by `execution`, replacing the no-ops in `kinevo-app`.
> **Abuse cases**: SEC-AC-21 start on another account's task → 404; SEC-AC-22 future `ended_at` → 422; SEC-AC-23 replaying `complete` with the same idempotency key does not double-emit. **EXE-FR-09**: no push or email (in-app only).

## Scope

**In:**
- Repositories, use cases, handlers, DTOs, `TaskCommandPort::transition` and `SessionGuardPort` implementations, wiring replacement, OpenAPI regeneration.

**Out:**
- Today view: T-202.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-execution/src/{app,infra,http}/sessions.rs` | create |  |
| `crates/kinevo-ports/src/{tasks_command,sessions}.rs` | modify | add `transition`; `running_slots` already exists |
| `crates/kinevo-mod-tasks/src/infra/command_port.rs` | modify | implement `transition` |
| `crates/kinevo-mod-execution/tests/{sessions_api,atomicity}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-app/src/modules.rs` | modify | replace no-ops |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
async fn transition(&self, tx: &mut T, task: TaskId, to: TaskStatus, actor: Actor) -> Result<TaskSnapshot, AppError>;
```

## Business rules

1. Start and complete are single transactions; any failure changes nothing.
2. `done` → task `completed`, `partial` → task `partial`.
3. `base_version` is mandatory for complete and abandon.
4. A replay with the same `Idempotency-Key` returns the stored response.

## Security

- Foreign task or session ids → 404.
- Notes are never logged.
- The actor token cannot be built from HTTP input.

## Implementation notes

- Compute `minutes` once in the use case and carry it in the event so progress never re-derives it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a startable task **When** two starts race **Then** one 201 and one 409 `session_already_running` with ids | `sessions_api::exe_fr_04_concurrent_start` |
| AC-2 | **Given** a task in `completed` **When** started **Then** 409 `task_not_startable` with `details.status` | `sessions_api::task_not_startable` |
| AC-3 | **Given** a running session **When** completed `done` **Then** session `completed`, task `completed`, events emitted, in one transaction | `sessions_api::exe_fr_05_complete_updates_task` |
| AC-4 | **Given** failure injected after each statement of complete **When** run **Then** no half state | `atomicity::exe_fr_05_fault_injection` |
| AC-5 | **Given** a future `ended_at` **When** completing **Then** 422 `ended_at_invalid` and nothing changes | `sessions_api::sec_ac_22_future_ended_at` |
| AC-6 | **Given** abandon with and without a current assignment **When** applied **Then** the task returns to `scheduled` or `backlog` | `sessions_api::exe_fr_06_abandon_target_status` |
| AC-7 | **Given** the same idempotency key **When** complete is replayed **Then** the same body and one event | `sessions_api::sec_ac_23_replay_single_event` |
| AC-8 | **Given** another account's task **When** started **Then** 404 | `sessions_api::sec_ac_21_foreign_task_not_found` |
| AC-9 | **Given** a stale running session **When** fetched **Then** `stale=true` | `sessions_api::exe_fr_07_stale_flag` |

## Tests

```
cargo nextest run -p kinevo-mod-execution --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-tasks --features kinevo-db/testing
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
