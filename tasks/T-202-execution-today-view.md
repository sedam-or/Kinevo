---
id: T-202
title: Implement the Today view with Now/Next, timeline, counts and graceful degradation
milestone: M4
kind: feature
status: todo
depends_on: [T-201]
requirements: [EXE-FR-01, EXE-FR-02, EXE-FR-03, EXE-FR-08, D-17, CA-10, J1]
branch: m4-t202-execution-today-view
budget_lines: 400
assignee: ""
---

# T-202: Implement the Today view with Now/Next, timeline, counts and graceful degradation

## Goal

One call returns everything the Today screen needs, computed on the server from the injected clock, and it still answers when an optional enrichment fails.

## Context

> **Endpoint** (`spec/modules/execution.md` §2–§3): `GET /today?date=YYYY-MM-DD` (default: today in the account timezone; `date_out_of_range` 422 beyond ±366 days with `details.min/max`) → `TodayView { server_now, date, timezone, now, next, timeline[], running_session, counts{completed,total}, schedule{review_state,pending_drafts}, unscheduled_open_tasks, warnings[] }`; `TodayItem = {kind:"task", assignment_id, task{id,title,status,priority_tier,goal{id,title}|null,workspace_id,progress_pct,subtasks_done,subtasks_total}, start_at,end_at,locked}`; `LandscapeItem = {kind:"landscape", event_id,title,start_at,end_at,provenance}`.
> **EXE-FR-01**: `now` = (a) the task of the running session, else (b) the assignment with the earliest `start_at` such that `start_at − grace ≤ server_now < end_at` with `grace = execution.now_window_grace_minutes`, ties by `task_id`; `next` = the earliest assignment with `start_at > server_now` other than `now`. **EXE-FR-02**: the day is the account-local calendar date (23 h and 25 h DST days); `timeline` holds landscape occurrences and assignments overlapping the day, ordered by `(start_at, kind, id)`. **EXE-FR-03**: `counts.total` = tasks with a current assignment starting that local date, `completed` = those with status `completed`. **EXE-FR-08**: if scheduler data is unavailable the response still returns the landscape timeline plus `warnings: ["module_degraded:scheduler"]`, never 500 for an optional enrichment.
> **Ports** (new, in `kinevo-ports`, with fakes): `ScheduleReadPort::day(tx, window) -> ScheduleDay { assignments[], review_state, pending_drafts }` implemented by `scheduler`; `LandscapeReadPort::items(tx, window) -> Vec<LandscapeItemDto>` (titles and provenance; added to the T-153 port, implemented by `landscape`); `TaskSummaryPort` and `GoalTitlePort` (T-158); `UnscheduledCountPort::count(tx)` implemented by `tasks` (eligible tasks with no current assignment, via `ScheduleCommandPort::has_current_assignment` semantics in one query on `schedule` through the scheduler port `ScheduleReadPort::assigned_task_ids`).
> **Time**: the account timezone comes from the identity port; `jiff` is used inside the module for local-day boundaries; all arithmetic on instants; the clock is injected.

## Scope

**In:**
- Domain `today.rs` (pure now/next and counts functions over DTOs), use case `GetToday`, handler, DTOs, the new ports with fakes and implementations, wiring, OpenAPI regeneration.

**Out:**
- Web: T-203.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-execution/src/{domain,app,infra,http}/today.rs` | create |  |
| `crates/kinevo-ports/src/{schedule_read,landscape,unscheduled}.rs` | create | ports and fakes; `landscape.rs` extended |
| `crates/kinevo-mod-scheduler/src/infra/schedule_read_port.rs, crates/kinevo-mod-landscape/src/infra/items_port.rs, crates/kinevo-mod-tasks/src/infra/unscheduled.rs` | create | implementations |
| `crates/kinevo-mod-execution/tests/{today_domain,today_api}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-app/src/modules.rs` | modify | wire |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn now_and_next(items: &[AssignmentItem], running: Option<&Session>, server_now: Instant, grace: Minutes) -> (Option<AssignmentItem>, Option<AssignmentItem>);
pub fn local_day_range(date: Date, tz: &TimeZone) -> TimeRange;
```

## Business rules

1. Now/Next is computed from `server_now` only; the browser never decides it.
2. The day view is always computed on read (no stored read model).
3. A failing optional port adds a warning; a failing required read (tasks) is a real error.

## Security

- All reads run in the caller's account `Tx` (RLS).
- Task titles are S2: not logged.

## Implementation notes

- Boundaries: at `start_at − grace` exactly the assignment becomes `now`; at `end_at` exactly it stops being `now` (half-open).
- Test the DST days in `Europe/Berlin` (25 h and 23 h).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** assignments around a slot boundary **When** the clock is at boundary −1 s, at `start_at − grace` and at `end_at` **Then** now/next switch exactly as specified | `today_domain::exe_fr_01_now_next_boundaries` |
| AC-2 | **Given** a running session **When** Today is read **Then** its task is `now` regardless of slots | `today_domain::exe_fr_01_running_session_wins` |
| AC-3 | **Given** a DST day **When** the timeline is built **Then** the day is 23 h or 25 h and items overlapping it are included | `today_domain::exe_fr_02_dst_day_timeline` |
| AC-4 | **Given** assignments and completed tasks **When** counts are read **Then** `total` and `completed` follow EXE-FR-03 | `today_api::exe_fr_03_counts` |
| AC-5 | **Given** the scheduler port failing **When** Today is read **Then** 200 with the landscape timeline and `module_degraded:scheduler` | `today_api::exe_fr_08_graceful_degradation` |
| AC-6 | **Given** a date beyond ±366 days **When** requested **Then** 422 `date_out_of_range` with min and max | `today_api::date_out_of_range` |
| AC-7 | **Given** two accounts **When** Today is read **Then** only own rows appear | `today_api::sec_ac_01_today_isolated` |
| AC-8 | **Given** a pending draft and `needs_review` **When** Today is read **Then** the `schedule` block reflects both | `today_api::schedule_block_reflects_state` |
| AC-9 | **Given** eligible tasks with no assignment **When** Today is read **Then** `unscheduled_open_tasks` counts them | `today_api::unscheduled_nudge_count` |

## Tests

```
cargo nextest run -p kinevo-mod-execution --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
