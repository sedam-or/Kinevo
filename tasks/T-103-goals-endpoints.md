---
id: T-103
title: Implement the goal state machine and the goal endpoints
milestone: M2
kind: feature
status: todo
depends_on: [T-102, T-101, T-017, T-019, T-014]
requirements: [GOAL-FR-01, GOAL-FR-03, GOAL-FR-04, GOAL-FR-06, GOAL-FR-07, GOAL-FR-08, SEC-AC-12]
branch: m2-t103-goals-endpoints
budget_lines: 400
assignee: ""
---

# T-103: Implement the goal state machine and the goal endpoints

## Goal

Goals can be created, read, edited, moved through their status machine, listed and deleted (with or without detaching tasks), with `allowed_transitions` computed by the server.

## Context

> **Endpoints** (`spec/modules/goals.md` §2): `GET /goals?workspace_id=&status=&sort=` (sort `target_date`, `created_at`, `title`; default `created_at`; archived/achieved/abandoned excluded unless `status` says so, GOAL-FR-08); `POST /goals {workspace_id?, title, description?, target_date?}` (workspace defaults to the default workspace; `workspace_archived`, `not_found`); `GET /goals/{id}` with `progress { milestones_done, milestones_total, tasks_done, tasks_total }`; `PATCH /goals/{id}` (If-Match; title, description, target_date); `POST /goals/{id}/status {to, base_version}`; `DELETE /goals/{id}?detach_tasks=bool` (If-Match; `goal_has_tasks` with `details.count`).
> **State machine** (§4): active↔paused, active↔achieved, active|paused→abandoned, abandoned→active; same state is a 200 no-op without version change; other pairs → 409 `invalid_transition` with `details.from/to`. Every goal representation carries `allowed_transitions` computed from the machine (SM-R6, SM-T2); no client hard-codes rules.
> **GOAL-FR-01**: `PATCH` with `workspace_id` → 422 `unknown_field`. **GOAL-FR-04**: progress counts come from the owning modules via `ProgressReadPort`, consistent within the request (single read transaction). **GOAL-FR-06**: delete with `detach_tasks=true` sets tasks' `goal_id`/`milestone_id` null in the same transaction through `TaskDetachPort`; with `false` and existing tasks → `goal_has_tasks`. **GOAL-FR-07**: title bounds follow `goals.title_max_chars`. **Validation**: description `long_text` ≤ 20 000, no HTML; `target_date` date 1970-01-01…2100-12-31.
> **Events**: `GoalCreated`, `GoalStatusChanged`, `GoalDeleted`. **Ports to add** (`kinevo-ports`): `ProgressReadPort::goal_task_counts(tx, goal_id) -> TaskCounts { done, total }` (implemented by tasks, T-107), `TaskDetachPort::detach_goal(tx, goal_id) -> u64` (tasks), `GoalLookup` (`exists_in_workspace(tx, goal_id) -> Option<GoalInfo { workspace_id, status }>`, implemented here for tasks). Fakes behind `testing`; the real task-side implementations arrive in T-107, so `kinevo-app` wires `NoTasks` fakes until then (documented).
> **SEC-AC-12**: foreign workspace id in create → 404.

## Scope

**In:**
- Pure domain: `Goal`, `GoalStatus`, `transition(from, to) -> Transition`, `allowed(from) -> Vec<GoalStatus>`; use cases and handlers for the six endpoints; `WorkspaceUsagePort` implementation counting goals; the three ports with fakes; OpenAPI regeneration.

**Out:**
- Milestones: T-104. Task-side port implementations: T-107.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/{progress,goals}.rs` | create | ports and fakes |
| `crates/kinevo-mod-goals/src/domain/goal.rs` | create | pure state machine |
| `crates/kinevo-mod-goals/src/{app,infra,http}/goals.rs` | create |  |
| `crates/kinevo-mod-goals/tests/{goal_machine,goals_api}.rs` | create | AC-1…AC-9 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub enum GoalStatus { Active, Paused, Achieved, Abandoned }
pub fn transition(from: GoalStatus, to: GoalStatus) -> Result<Transition, InvalidTransition>;   // Transition::{Changed, NoOp}
pub fn allowed(from: GoalStatus) -> &'static [GoalStatus];
```

## Business rules

1. Machine table-tested over all 16 (from, to) pairs.
2. `achieved` is allowed with open milestones; milestones and tasks are untouched.
3. A same-state request returns 200 with the unchanged `version`.
4. Delete with `detach_tasks=true` runs detach and delete in one transaction.

## Security

- Mass assignment (`account_id`, `id`, `version`, `status` on `PATCH`, `workspace_id`) → 422 `unknown_field`.
- Foreign ids → 404; all SQL in the account `Tx`.

## Implementation notes

- Compute `allowed_transitions` in the domain and serialise it in the DTO; the web uses it (T-110).
- Excluding non-active goals by default is a query default, not a status rule.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** all 16 status pairs **When** evaluated **Then** exactly the table's transitions succeed and same-state is a no-op | `goal_machine::goal_fr_03_transition_table` |
| AC-2 | **Given** a goal **When** read **Then** `allowed_transitions` equals `allowed(status)` | `goals_api::sm_t2_allowed_transitions_in_dto` |
| AC-3 | **Given** `PATCH` with `workspace_id` **When** sent **Then** 422 `unknown_field` | `goals_api::goal_fr_01_workspace_immutable` |
| AC-4 | **Given** title lengths max and max+1 under the policy **When** creating **Then** accepted then 422 | `goals_api::goal_fr_07_title_bounds` |
| AC-5 | **Given** a goal with tasks (fake) **When** deleting with `detach_tasks=false` **Then** 409 `goal_has_tasks`; with true tasks are detached and the goal is gone | `goals_api::goal_fr_06_delete_detach_modes` |
| AC-6 | **Given** achieved and abandoned goals **When** listing by default **Then** excluded; with `status` included | `goals_api::goal_fr_08_default_list_excludes_closed` |
| AC-7 | **Given** fake task counts **When** reading a goal **Then** progress reflects them from one transaction | `goals_api::goal_fr_04_progress_from_port` |
| AC-8 | **Given** a foreign workspace id **When** creating **Then** 404 | `goals_api::sec_ac_12_foreign_workspace_not_found` |
| AC-9 | **Given** an archived workspace **When** creating a goal **Then** 409 `workspace_archived` | `goals_api::archived_workspace_blocks_create` |

## Tests

```
cargo nextest run -p kinevo-mod-goals --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
