# Module `goals`

Kind: core. Depends on: `workspaces`. Tables: `goals`, `milestones`. Prefix: `GOAL`.
A **goal** is an outcome the user cares about; **milestones** are ordered checkpoints toward it.
Tasks hang off goals/milestones (`modules/tasks.md`).

## 1. Permissions

`goals.goal.read|create|update|delete`, `goals.milestone.read|create|update|delete` — `account_owner` and `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /goals?workspace_id=&status=&sort=` | 200 page (`sort`: `target_date`, `created_at`, `title`; default `created_at`) | — |
| `POST /goals` `{ workspace_id?, title, description?, target_date? }` | 201 (workspace defaults to the default workspace) | `workspace_archived` 409, `not_found` (workspace), validation 422 |
| `GET /goals/{id}` | 200 incl. `progress` summary (`milestones_done`, `milestones_total`, `tasks_done`, `tasks_total`) | `not_found` |
| `PATCH /goals/{id}` (If-Match) `{ title?, description?, target_date? }` | 200 | `version_conflict`, validation |
| `POST /goals/{id}/status` `{ to, base_version }` | 200 | `invalid_transition` 409, `version_conflict` |
| `DELETE /goals/{id}?detach_tasks=bool` (If-Match) | 204 | `goal_has_tasks` 409 (when `detach_tasks=false` and tasks exist) |
| `GET /goals/{id}/milestones` | 200 ordered by `position, id` | — |
| `POST /goals/{id}/milestones` `{ title, description?, target_date?, position? }` | 201 | `goal_not_active` 409 (goal is `achieved`/`abandoned`), validation |
| `PATCH /milestones/{id}` (If-Match) | 200 | `version_conflict` |
| `POST /milestones/{id}/status` `{ to: "open"|"done", base_version }` | 200 | `version_conflict` |
| `POST /goals/{id}/milestones/reorder` `{ ids: [uuid…] }` | 200 | `reorder_set_mismatch` 422 (ids must be exactly the goal's milestones) |
| `DELETE /milestones/{id}` (If-Match) | 204; tasks keep `goal_id` and lose `milestone_id` | — |

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| GOAL-FR-01 | A goal belongs to exactly one workspace, chosen at creation; its workspace cannot change (moving is a copy-and-archive operation, out of scope). | `PATCH` with `workspace_id` → 422 `unknown_field`. |
| GOAL-FR-02 | `target_date` is a calendar date in the account's timezone. A milestone's `target_date` must not be after its goal's `target_date` when both are set → 422 `milestone_after_goal`. | Table test. |
| GOAL-FR-03 | Goal status machine (§4). Setting `achieved` is allowed even with open milestones (the UI warns); it does not alter milestones or tasks. | Test. |
| GOAL-FR-04 | `GET /goals/{id}` progress counts come from the owning modules through `ProgressReadPort`; counts are consistent within the request (single read transaction). | Test with concurrent writes. |
| GOAL-FR-05 | Milestone `done` is manual. When the last open milestone of a goal becomes `done`, a `GoalMilestonesAllDone` event is emitted so the UI can suggest "mark goal achieved"; nothing changes automatically. | Event test. |
| GOAL-FR-06 | Deleting a goal with `detach_tasks=true` sets `goal_id`/`milestone_id` of its tasks to null in the same transaction; with `false` and existing tasks it returns `goal_has_tasks` (`details.count`). Notes/canvases links to the goal are removed by their modules reacting to `GoalDeleted`. | Cross-module test. |
| GOAL-FR-07 | Title bounds follow `goals.title_max_chars`. | Boundary test at max and max+1. |
| GOAL-FR-08 | Abandoned/achieved goals are excluded from the default `GET /goals` (the `status` filter includes them explicitly). Tasks of any goal whose status is not `active` (`paused`, `achieved`, `abandoned`) are **excluded from new scheduler drafts**; their existing assignments stay until the user applies a draft, and the scheduler flags `needs_review` (SCH-FR-12). Unanchored tasks and tasks of `active` goals are included. | Scheduler input test. |

## 4. State machine — goal

| From | To | Guard | Effect | Failure/recovery |
|---|---|---|---|---|
| `active` | `paused` | — | `GoalStatusChanged` | — |
| `paused` | `active` | — | same | — |
| `active` | `achieved` | — | same; `achieved_at` not stored (use `updated_at` of the event) | — |
| `achieved` | `active` | — | same | — |
| `active`, `paused` | `abandoned` | — | same; scheduler subscriber removes this goal's *future auto* assignments into the next draft proposal (never silently deleting; produces `needs_review`) | — |
| `abandoned` | `active` | — | same | — |
| any | same state | — | 200 no-op (idempotent), version unchanged | — |
| other pairs | — | — | 409 `invalid_transition` (`details.from`, `details.to`) | client reloads |

Note on scheduling: see GOAL-FR-08. Existing assignments are never removed automatically.

## 5. Validation

`title`: `short_text` 1–`goals.title_max_chars`; `description`: `long_text` ≤ 20 000 chars (plain text with line breaks, no HTML; rendered as text); `target_date`: `date` within 1970-01-01…2100-12-31; milestone `position`: int 0–10000.

## 6. Events

Emits `GoalCreated`, `GoalStatusChanged`, `GoalDeleted`, `MilestoneCreated`, `MilestoneStatusChanged`, `MilestoneDeleted`, `GoalMilestonesAllDone`. Consumes `TaskCompleted`/`TaskReopened` (no state change here; counts are computed on read).

## 7. Abuse cases

Cross-account goal id in `task.goal_id` → 404; mass-assignment attempt (`PATCH` with `account_id`, `version`, `id`) → 422 `unknown_field`.
