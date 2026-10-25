# Module `execution` (Today and focus sessions)

Kind: core. Depends on: `tasks`, `scheduler`. Tables: `focus_sessions`. Prefix: `EXE`.
This module delivers the first-love loop: **open the app → see what to do now → start → finish → see what is next**.

## 1. Permissions

`execution.today.read`, `execution.session.read|start|complete|abandon` — `account_owner`, `instance_admin` (own data).

## 2. Endpoints

| Method / path | Success | Errors |
|---|---|---|
| `GET /today?date=YYYY-MM-DD` (default: today in the account timezone) | 200 `TodayView` (§3) | `date_out_of_range` 422 (±366 days from today) |
| `POST /focus-sessions` `{ task_id, assignment_id?, target_minutes? }` + `Idempotency-Key` | 201 session | `session_already_running` 409 (`details.session_id`, `details.task_id`), `task_not_startable` 409 (`details.status`), `not_found` |
| `GET /focus-sessions/current` | 200 session or 204 | — |
| `POST /focus-sessions/{id}/complete` `{ outcome: "done"\|"partial", note?, ended_at?, base_version }` | 200 `{ session, task }` | `session_not_running` 409, `ended_at_invalid` 422, `version_conflict` |
| `POST /focus-sessions/{id}/abandon` `{ base_version }` | 200 `{ session, task }` | `session_not_running`, `version_conflict` |
| `GET /focus-sessions?task_id=&from=&to=` | 200 page, newest first | — |

## 3. `TodayView`

```
{
  "server_now": instant, "date": "YYYY-MM-DD", "timezone": "Area/City",
  "now":  TodayItem | null,        // §4 rule
  "next": TodayItem | null,
  "timeline": [ TodayItem | LandscapeItem ],   // chronological, the whole local day
  "running_session": Session | null,
  "counts": { "completed": int, "total": int },   // tasks assigned today
  "schedule": { "review_state": "clean"|"needs_review", "pending_drafts": int },
  "unscheduled_open_tasks": int,                  // eligible tasks with no assignment (a nudge to plan)
  "warnings": [ "module_degraded:..." ]
}
TodayItem     = { kind:"task", assignment_id, task:{id,title,status,priority_tier,goal:{id,title}|null,workspace_id,progress_pct,subtasks_done,subtasks_total}, start_at, end_at, locked }
LandscapeItem = { kind:"landscape", event_id, title, start_at, end_at, provenance }
```

## 4. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| EXE-FR-01 | **Now/Next is computed by the server** from the injected clock (the browser may tick locally to re-render but re-fetches on focus, on reconnect and every 60 s while visible). `now` = (a) the task of the running session, else (b) the assignment with the earliest `start_at` such that `start_at − grace ≤ server_now < end_at`, where `grace = execution.now_window_grace_minutes`; ties by `task_id`. `next` = the earliest assignment with `start_at > server_now` other than `now`. | Table tests with an injected clock at slot boundaries and during DST changes. |
| EXE-FR-02 | The day's boundaries are the account-local calendar date; `timeline` includes landscape occurrences overlapping the day and assignments overlapping the day, ordered by `(start_at, kind, id)`. | DST-day test (23 h and 25 h days). |
| EXE-FR-03 | `counts.total` = number of tasks having a current assignment starting that local date; `counts.completed` = those with status `completed`. | Test. |
| EXE-FR-04 | **Start**: in one transaction — lock the account's session slot (the partial unique index `focus_one_running`), create the session `running` with `target_minutes` (default `execution.focus.default_minutes`), transition the task to `in_progress` through `TaskCommandPort` (allowed from `backlog`, `scheduled`, `continued`, `conflict`; others → `task_not_startable`). A second start while one is running → 409 `session_already_running`. | Concurrency test (two simultaneous starts → one 201, one 409). |
| EXE-FR-05 | **Complete**: in one transaction — set session `completed` with `ended_at` (default `server_now`; client-provided `ended_at` must satisfy `started_at ≤ ended_at ≤ server_now`), store `outcome`/`note`; transition the task: `done` → `completed`, `partial` → `partial`; emit `SessionCompleted` and the task events. A failure at any step rolls back all of it (legacy ran these steps without a transaction). | Fault-injection test: kill after each statement ⇒ no half state. |
| EXE-FR-06 | **Abandon**: session `abandoned`, task returns to `scheduled` if it has a current assignment else `backlog`; no progress event. | Test. |
| EXE-FR-07 | **Stale sessions**: a `running` session older than `execution.session.stale_after_minutes` is returned with `stale=true`. Completing a stale session without `ended_at` uses `started_at + target_minutes` (never `server_now`) so forgotten timers do not produce absurd durations. | Test. |
| EXE-FR-08 | Today degrades gracefully: if `scheduler` data is unavailable the response still returns `timeline` from landscape plus `warnings`; it never returns 500 for a partial failure of an optional enrichment. | Fault test on the scheduler port. |
| EXE-FR-09 | Notifications: **in-app only** in 1.0 (a banner when `now` changes while the app is open). No push/email/Android local notifications (D-27, PRD-X8). | — |
| EXE-FR-10 | The timer display is computed from `started_at` and `server_now` offset (clock skew corrected using the `server_now` of the last response), so it survives reloads and device sleep. | E2E test with a skewed browser clock. |

## 5. State machine — focus session

| From | Event | Guard | To | Failure/recovery |
|---|---|---|---|---|
| (none) | start | no running session; task startable | `running` | conflict → 409; idempotent replay returns the same session |
| `running` | complete | `started_at ≤ ended_at ≤ now` | `completed` | invalid `ended_at` → 422; nothing changes |
| `running` | abandon | — | `abandoned` | — |
| `completed`, `abandoned` | any | — | (terminal) | 409 `session_not_running` |

## 6. Data flow — complete

`POST complete` → use case: BEGIN → `SELECT … FOR UPDATE` session → check `base_version`/state → domain `Session::complete` → `UPDATE focus_sessions` → `TaskCommandPort::transition(task_id, to, actor=Execution)` (same `Tx`) → outbox `SessionCompleted`, `TaskStatusChanged`, `TaskCompleted` (if done) → COMMIT → respond. Subscribers (progress) run after commit and are idempotent on `source_event_id`.

## 7. Validation

`target_minutes`: int 1–1440; `note`: `long_text` ≤ 2 000; `outcome`: enum; `ended_at`: `instant`.

## 8. Events / jobs

Emits: `SessionStarted`, `SessionCompleted`, `SessionAbandoned`. Consumes: `AssignmentsApplied`, `TaskStatusChanged` (cache invalidation of the day view; the view itself is always computed on read — no stored read model in 1.0).
Jobs: none.

## 9. UI states

`today.loading`, `today.empty.no_tasks`, `today.empty.nothing_scheduled`, `today.now`, `today.next`, `today.review_needed`, `session.running`, `session.stale`, `session.conflict` (`standards/ux-states.md`).

## 10. Abuse cases

SEC-AC-21: start a session on another account's task → 404. SEC-AC-22: client supplies `ended_at` in the future → 422. SEC-AC-23: replaying `complete` with the same idempotency key does not double-count progress (unique `(account, source_event_id, kind)`).
