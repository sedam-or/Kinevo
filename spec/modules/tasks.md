# Module `tasks`

Kind: core. Depends on: `goals`. Tables: `tasks`, `subtasks`. Prefix: `TASK`.

## 1. Permissions

`tasks.task.read|create|update|delete|status`, `tasks.subtask.read|create|update|delete` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /tasks?workspace_id=&goal_id=&milestone_id=&status=&due_before=&title_contains=&unanchored=&sort=` | 200 page (`status` repeatable; sort: `due_at`, `priority_tier`, `created_at`, `title`) | `sort_invalid` |
| `POST /tasks` `{ title, workspace_id?, goal_id?, milestone_id?, notes?, priority_tier?, estimate_minutes?, due_at?, is_anchor? }` | 201 | `task_goal_workspace_mismatch`, `task_milestone_goal_mismatch`, `milestone_requires_goal`, `anchor_exists`, `workspace_archived`, validation |
| `GET /tasks/{id}` | 200 incl. `subtasks[]` | `not_found` |
| `PATCH /tasks/{id}` (If-Match) | 200 | same as create + `version_conflict` |
| `POST /tasks/{id}/status` `{ to, base_version, reason? }` | 200 | `invalid_transition` 409, `version_conflict`, `anchor_exists` (reopen) |
| `DELETE /tasks/{id}` (If-Match) | 204 | `task_has_running_session` 409 |
| `POST /tasks/{id}/subtasks` `{ title, position? }` | 201 (task `version` increments because progress changes) | `subtask_limit_reached` 409 |
| `PATCH /subtasks/{id}` (If-Match) `{ title?, completed?, position? }` | 200 | `version_conflict` |
| `DELETE /subtasks/{id}` (If-Match) | 204 | — |

Every task representation includes `allowed_transitions` computed by the task state machine for the current state (`standards/contracts.md`, SM-R6, SM-T2); the web renders status actions only from it and never hard-codes transition rules (CA-15).

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| TASK-FR-01 | Consistency: a task with `goal_id` must be in the goal's workspace (default workspace = goal's workspace when `workspace_id` is omitted; mismatch → 422 `task_goal_workspace_mismatch`); `milestone_id` requires `goal_id` and the milestone must belong to that goal. | Table test; DOM-20. |
| TASK-FR-02 | Defaults: `priority_tier` = `tasks.default_priority_tier`; `estimate_minutes` null (the scheduler substitutes `scheduler.default_task_minutes`); `due_at` null. | Test. |
| TASK-FR-03 | At most one non-terminal anchor task per account (`anchor_exists`, `details.task_id`). An anchor must have `estimate_minutes` null or equal to `scheduler.anchor.duration_minutes`; the scheduler always places it for exactly that duration (SCH-R3). | Tests. |
| TASK-FR-04 | Status transitions follow §4 and are applied only through `POST /tasks/{id}/status` or by system actors (scheduler, execution). The `to` accepted from the **user** is restricted to the rows marked ✓U. | Table test over all (from,to) pairs: allowed, rejected-with-`invalid_transition`. |
| TASK-FR-05 | `progress_pct` is derived: with subtasks, `(200*done + total) / (2*total)` using integer division (round half up); without subtasks, 0 until `completed` then 100. It is recomputed in the same transaction as every subtask or status change. | Property test: result in 0..100; monotone in `done`. |
| TASK-FR-06 | Completing a task (`to=completed`) with open subtasks is allowed; it marks no subtask. Completing sets `completed_at` from the injected clock; reopening clears it (D-36). | Test. |
| TASK-FR-07 | Editing `estimate_minutes`, `due_at`, `is_anchor`, `goal_id` or `status` of a task that has a *current* assignment emits `TaskSchedulingInputsChanged`; the scheduler marks the schedule `needs_review` if the assignment is no longer valid (SCH-FR-12). Nothing is moved automatically. | Event + scheduler test. |
| TASK-FR-08 | Deleting a task removes its subtasks, assignments and focus sessions (cascade) after refusing when a session is running. `TaskDeleted` is emitted. | Test. |
| TASK-FR-09 | Bulk operations are not part of 1.0 API; clients issue single requests (the sync batch covers offline replay). | — |
| TASK-FR-10 | `title_contains` is a case-insensitive substring match with `%`, `_` and `\` escaped; max 100 chars. | Injection/escaping test (SEC-AC-13). |

## 4. State machine — task status

Actors: **U** = user via API, **E** = execution module, **S** = scheduler module, **J** = job.
✓U = also directly requestable by the user.

| From | To | Actor | ✓U | Guard / effect |
|---|---|---|---|---|
| `backlog` | `scheduled` | S (apply draft) / U (manual place, via scheduler API) | — | has a current assignment |
| `backlog` | `in_progress` | E (start session) | — | no other running session |
| `backlog` | `completed` | U | ✓ | sets `completed_at` |
| `backlog` | `skipped` | U | ✓ | |
| `scheduled` | `in_progress` | E | — | |
| `scheduled` | `missed` | J (`tasks.mark_missed`) | — | assignment ended, never started; runs per policy cadence |
| `scheduled` | `conflict` | S | — | assignment became infeasible (landscape change) and could not be re-placed |
| `scheduled` | `skipped` | U | ✓ | assignment superseded |
| `scheduled` | `backlog` | U / S | ✓ | unschedule: assignment superseded |
| `in_progress` | `completed` | E / U | ✓ | ends the running session if any (E path) |
| `in_progress` | `partial` | E | — | session outcome `partial` |
| `in_progress` | `conflict` | S | — | |
| `in_progress` | `skipped` | U | ✓ | abandons running session |
| `partial` | `continued` | U / S | ✓ | user commits to continue |
| `partial` | `completed` | U | ✓ | |
| `partial` | `scheduled` | S | — | re-placed |
| `partial` | `skipped` | U | ✓ | |
| `continued` | `scheduled` / `in_progress` | S / E | — | |
| `continued` | `completed` / `skipped` | U | ✓ | |
| `missed` | `backlog` | U | ✓ | |
| `missed` | `scheduled` | S | — | |
| `missed` | `completed` | U | ✓ | "I did it anyway" |
| `conflict` | `scheduled` | S | — | re-placed |
| `conflict` | `in_progress` | E | — | user starts anyway |
| `conflict` | `backlog` | U | ✓ | |
| `completed`, `skipped` | `backlog` | U | ✓ | **reopen** (D-36): clears `completed_at`; anchor guard applies |
| any | same | — | — | 200 no-op, version unchanged |
| anything else | — | — | — | 409 `invalid_transition` |

Failure/recovery: a status change that fails its guard changes nothing; the response carries the current state and `version` so the client can re-render (`standards/ux-states.md` `conflict.task`).

## 5. Data flow — change status

`POST /tasks/{id}/status` → `UpdateTaskStatus` use case, one transaction: load task `FOR UPDATE` → check `base_version` → domain `Task::transition(to, actor, now)` → persist → (if leaving `scheduled`/`backlog` with an assignment: call `ScheduleCommandPort::supersede_assignment(task_id)` in the **same** transaction) → append outbox `TaskStatusChanged` (and `TaskCompleted` / `TaskReopened`). Subscribers (progress, goals counters, scheduler) run after commit.

## 6. Validation

| Field | Kind | Rule |
|---|---|---|
| `title` | `short_text` | 1–`tasks.title_max_chars` |
| `notes` | `long_text` | ≤ 20 000, plain text |
| `priority_tier` | `int` | 1–3 |
| `estimate_minutes` | `int` or null | 1–1440 |
| `due_at` | `instant` or null | RFC 3339; any date 1970…2100 (past due allowed) |
| `subtask.title` | `short_text` | 1–500; max `tasks.max_subtasks` per task |

## 7. Events / jobs

Emits: `TaskCreated`, `TaskUpdated`, `TaskSchedulingInputsChanged`, `TaskStatusChanged`, `TaskCompleted`, `TaskReopened`, `TaskDeleted`. Consumes: `GoalDeleted` (detach handled in the goals transaction), `AssignmentSuperseded`/`AssignmentCreated` (scheduler) to keep `scheduled`/`backlog` in step if an assignment is removed outside a task command.
Jobs: `tasks.mark_missed` — on every job tick (`KINEVO_JOBS_TICK_SECONDS`, bootstrap environment variable, default 300) per account with `scheduled` tasks whose current assignment ended more than `execution.now_window_grace_minutes` ago and never started.

## 8. Abuse cases

SEC-AC-13: `title_contains=%` returns only literal matches. SEC-AC-14: mass assignment of `status`, `completed_at`, `progress_pct`, `account_id` via `PATCH` → 422 `unknown_field`. SEC-AC-15: task id of another account in any command → 404.
