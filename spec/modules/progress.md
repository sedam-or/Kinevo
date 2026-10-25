# Module `progress`

Kind: core. Depends on: `tasks`, `goals`. Tables: `progress_events`. Prefix: `PRG`.
Answers "what moved?" with **facts**, not scores. No streaks, points or badges (PRD principle: boring and honest).

## 1. Permissions

`progress.summary.read`, `progress.goals.read`, `progress.review.read` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /progress/summary?from_date=&to_date=&workspace_id=` | 200 `{ days:[{date, tasks_completed, tasks_reopened, focus_minutes}], totals }` | `window_too_large` 422 (> 92 days) |
| `GET /progress/goals?workspace_id=&status=` | 200 `[{ goal_id, title, status, target_date, milestones:{done,total}, tasks:{done,open,total,overdue}, goal_overdue }]` | — |
| `GET /progress/review?week_of=YYYY-MM-DD` | 200 `{ week_start, week_end, completed, missed, partial, focus_minutes, by_workspace:[…], missed_tasks:[{task_id,title}] }` (week starts on the account's `week_start`) | — |

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| PRG-FR-01 | `progress_events` are written by this module as an outbox **subscriber** of `TaskCompleted`, `TaskReopened`, `TaskStatusChanged(partial|missed)`, `MilestoneStatusChanged(done)`, `GoalStatusChanged(achieved)`, `SessionCompleted`. Each event carries `source_event_id`; the unique key `(account_id, source_event_id, kind)` makes replays no-ops. | Replay test: deliver each event twice ⇒ one row. |
| PRG-FR-02 | `local_date` is computed from `occurred_at` in the account timezone **at the time of the event** (stored, not recomputed), so changing timezone later does not rewrite history. | Test. |
| PRG-FR-03 | `tasks_completed` for a day = (`task_completed` events that day) − (`task_reopened` events that day), clamped at ≥ 0, so an undo on the same day cancels the completion. A reopen on a later day reduces that later day (history is not rewritten). | Table test. |
| PRG-FR-04 | `focus_minutes` for a day = sum over `session_completed` events of whole minutes `floor((ended_at − started_at)/60 s)` carried in the event payload. | Test. |
| PRG-FR-05 | `/progress/goals` counts are read-time aggregates through ports (`GoalReadPort`, `TaskReadPort`) in one read-only transaction; `overdue` = open tasks with `due_at < now`; `goal_overdue` = goal `active` with `target_date` before today. Neither is a "risk score". | Test with injected clock. |
| PRG-FR-06 | `/progress/review` `missed` = tasks that moved to `missed` during the week (events), `partial` likewise. | Test. |
| PRG-FR-07 | Workspace filtering applies to all three endpoints; absent filter = all non-archived workspaces. | Test. |
| PRG-FR-08 | If `progress` falls behind (events pending), responses include `freshness: { pending_events: n }` so the UI can show a "updating" hint; values are eventually consistent within `jobs` dispatch latency (target < 5 s `[ASSUMED]`). | Test. |

## 4. Events / jobs

Emits none. Consumes the events in PRG-FR-01. Job `progress.rebuild` (admin CLI `kinevo admin rebuild-progress <account>`): recomputes `progress_events` from source tables for repair; idempotent.

## 5. Abuse cases

SEC-AC-24: `workspace_id` of another account → empty result is **not** allowed; returns 404 (existence not leaked). Contract test.
