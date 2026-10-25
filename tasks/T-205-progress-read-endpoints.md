---
id: T-205
title: Implement the progress summary, goals and weekly review endpoints
milestone: M4
kind: feature
status: todo
depends_on: [T-204]
requirements: [PRG-FR-03, PRG-FR-05, PRG-FR-06, PRG-FR-07, PRG-FR-08, SEC-AC-24, CA-10]
branch: m4-t205-progress-read-endpoints
budget_lines: 380
assignee: ""
---

# T-205: Implement the progress summary, goals and weekly review endpoints

## Goal

Users can see what moved per day, per goal and per week as plain facts, with no scores and no leaked existence of other accounts' workspaces.

## Context

> **Endpoints** (`spec/modules/progress.md` §2): `GET /progress/summary?from_date=&to_date=&workspace_id=` → `{days:[{date,tasks_completed,tasks_reopened,focus_minutes}], totals}` (`window_too_large` 422 above 92 days); `GET /progress/goals?workspace_id=&status=` → `[{goal_id,title,status,target_date,milestones:{done,total},tasks:{done,open,total,overdue},goal_overdue}]`; `GET /progress/review?week_of=YYYY-MM-DD` → `{week_start,week_end,completed,missed,partial,focus_minutes,by_workspace[],missed_tasks[{task_id,title}]}` (the week starts on the account's `week_start`, ISO weekday).
> **PRG-FR-03** `tasks_completed` for a day = `task_completed` events that day − `task_reopened` events that day, clamped at ≥ 0; a reopen on a later day reduces that later day only. **PRG-FR-04** `focus_minutes` = sum of event minutes. **PRG-FR-05** goal counts are read-time aggregates through `ProgressReadPort`/`GoalReadPort`/`TaskReadPort` in one read-only transaction; `overdue` = open tasks with `due_at < now`; `goal_overdue` = goal `active` with `target_date` before today (local); neither is a risk score. **PRG-FR-06** `missed`/`partial` come from events during the week; `missed_tasks` titles via `TaskSummaryPort`. **PRG-FR-07** workspace filter applies to all three; no filter = all non-archived workspaces. **PRG-FR-08** responses carry `freshness: {pending_events}` (undelivered outbox deliveries for this module's subscribers). **SEC-AC-24** a `workspace_id` of another account → 404, never an empty result.
> **No streaks, points or badges** (module header).
> **Ports**: read-only use of `ProgressReadPort` (T-107), `TaskSummaryPort` (T-158), a new `GoalListPort::goals(tx, filter) -> Vec<GoalProgressDto>` implemented by goals (title, status, target date, milestone counts) and `WorkspaceLookup` (T-101). The clock is injected.

## Scope

**In:**
- Domain functions (`day_series`, `week_bounds`), use cases, handlers, DTOs, `GoalListPort` with fake and implementation, wiring, OpenAPI regeneration.

**Out:**
- Web: T-206.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-progress/src/{domain,app,infra,http}/reads.rs` | create |  |
| `crates/kinevo-ports/src/goal_list.rs` | create | port and fake |
| `crates/kinevo-mod-goals/src/infra/goal_list_port.rs` | create | implementation |
| `crates/kinevo-mod-progress/tests/{summary_api,goals_api,review_api,series}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-app/src/modules.rs` | modify | wire |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |

## Interfaces

```rust
pub fn day_series(events: &[ProgressEvent], from: Date, to: Date) -> Vec<DayStat>;
pub fn week_bounds(date: Date, week_start: IsoWeekday) -> (Date, Date);
```

## Business rules

1. Same-day undo nets to zero; later-day reopen reduces the later day.
2. Every day in the window is present in `days` with zeros.
3. Week boundaries use the account's `week_start`.

## Security

- Foreign workspace → 404 (SEC-AC-24).
- Queries run in the account `Tx`.

## Implementation notes

- `overdue` counts use the injected clock and the task `due_at`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a completion and a reopen on the same day **When** summarised **Then** net 0; a later-day reopen reduces only that day | `series::prg_fr_03_net_per_day` |
| AC-2 | **Given** session events **When** summarised **Then** `focus_minutes` sums event minutes | `summary_api::prg_fr_04_focus_minutes` |
| AC-3 | **Given** a window over 92 days **When** requested **Then** 422 `window_too_large` | `summary_api::window_too_large` |
| AC-4 | **Given** goals with open, done and overdue tasks **When** read **Then** counts and `goal_overdue` follow PRG-FR-05 | `goals_api::prg_fr_05_counts_with_injected_clock` |
| AC-5 | **Given** missed and partial events in a week **When** reviewed **Then** lists and `missed_tasks` match | `review_api::prg_fr_06_missed_and_partial` |
| AC-6 | **Given** a `week_start` of Sunday **When** reviewed **Then** the week bounds follow it | `series::week_bounds_use_account_week_start` |
| AC-7 | **Given** a workspace filter **When** applied **Then** all three endpoints filter; absent means non-archived workspaces | `summary_api::prg_fr_07_workspace_filter` |
| AC-8 | **Given** another account's workspace id **When** requested **Then** 404 | `summary_api::sec_ac_24_foreign_workspace_404` |
| AC-9 | **Given** undelivered events **When** read **Then** `freshness.pending_events` is non-zero | `summary_api::prg_fr_08_freshness` |

## Tests

```
cargo nextest run -p kinevo-mod-progress --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-goals --features kinevo-db/testing
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
