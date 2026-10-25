---
id: T-158
title: Add the task and deadline read ports and the pure input-assembly function
milestone: M3
kind: feature
status: todo
depends_on: [T-157]
requirements: [SCH-FR-06, SCH-FR-15, SCH-FR-16, D-17, D-33, CA-10, CA-03]
branch: m3-t158-scheduler-input-assembly
budget_lines: 400
assignee: ""
---

# T-158: Add the task and deadline read ports and the pure input-assembly function

## Goal

The scheduler can build a correct, time-zone-aware `SchedulerInput` for any horizon from tasks, deadlines, landscape and policies, with no module reading another module's tables.

## Context

> **Assembly** (`spec/modules/scheduler.md` SCH-FR-06): eligible tasks have status in {`backlog`,`scheduled`,`partial`,`continued`,`missed`,`conflict`} and (no goal, or goal `active`); `in_progress` tasks contribute their slot as `fixed`; terminal tasks are excluded. `duration_minutes = estimate_minutes ?? scheduler.default_task_minutes` (anchor: `scheduler.anchor.duration_minutes`). Deadlines: goal/milestone `target_date` → the instant at the **start of the next local day** (exclusive end of the target date) in the account timezone; task `due_at` as is. Days: for each local date intersecting the horizon, window = `[date@scheduler.day_start, date@scheduler.day_end)` ∩ horizon; `anchor_not_before = max(window.start, date@scheduler.anchor.earliest_time)`. Local-time to instant conversion follows the landscape DST rule (a nonexistent local time moves forward by the gap; an ambiguous one takes the earlier instant); a day whose window collapses is dropped.
> **SCH-FR-15** the schedule is account-wide: tasks of every workspace compete. **SCH-FR-16** the horizon start is clamped to `now` rounded up to the next `scheduler.min_slot_minutes` boundary (injected `Clock`); nothing is created in the past.
> **Ports** (CA-03/CA-10, new in `kinevo-ports`, with fakes behind `testing`): `SchedulableTaskPort::list_schedulable(tx) -> Vec<SchedTaskDto>` (implemented by `tasks`: id, status, duration estimate, priority tier, anchor flag, `due_at`, goal id, milestone id, `progress_pct`, workspace id) and `DeadlineLookupPort::deadlines(tx, goal_ids, milestone_ids) -> DeadlineMap` plus `goal_active(tx, ids)` (implemented by `goals`: `target_date` per goal/milestone and goal status). `TaskSummaryPort::summaries(tx, ids) -> Vec<TaskSummaryDto>` (tasks: id, title, status, priority tier, goal id, workspace id, `progress_pct`, subtasks done/total) and `GoalTitlePort::titles(tx, ids)` (goals), used by `GET /schedule/assignments` and Today; `LandscapeReadPort` exists (T-153); policies come from `PolicyReader`; the account timezone from the identity port already in `kinevo-ports` (T-008).
> **Policies read** (04-policies §3): `scheduler.min_slot_minutes`, `reserve_bp`, `day_start`, `day_end`, `anchor.duration_minutes`, `anchor.earliest_time`, `daily_capacity_bp`, `default_task_minutes`, `draft_horizon_days`, `sync_max_horizon_days`; the exact values used are returned as the `policy_snapshot`.
> **Time zones** stay in the module (`jiff`), never in `kinevo-scheduler`.

## Scope

**In:**
- Ports and fakes in `kinevo-ports`; implementations in `kinevo-mod-tasks` and `kinevo-mod-goals` (read-only queries inside the caller's `Tx`); `kinevo-app` wiring replaces nothing yet.
- `kinevo-mod-scheduler::domain::assemble`: `assemble(ctx: AssembleCtx) -> Assembled { input, policy_snapshot, warnings }` as a pure function over DTOs, plus `app::input_builder` that gathers the DTOs through the ports; `input_hash` helper (SHA-256 of the canonical input).
- Reschedule input builder (§2.8): fixed = locked/manual + still-feasible current assignments (revalidated with `validate`, own range excluded); tasks = unscheduled or infeasible, with `current_slot`.

**Out:**
- Endpoints: T-159. Apply: T-160.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/{scheduling,deadlines}.rs` | create | ports and fakes |
| `crates/kinevo-mod-tasks/src/infra/schedulable.rs` | create | port implementation |
| `crates/kinevo-mod-goals/src/infra/deadlines.rs` | create | port implementation |
| `crates/kinevo-mod-scheduler/src/domain/assemble.rs, src/app/input_builder.rs` | create |  |
| `crates/kinevo-mod-scheduler/tests/{assemble,reschedule}.rs` | create | AC-1…AC-9 |
| `crates/kinevo-mod-tasks/tests/schedulable_port.rs, crates/kinevo-mod-goals/tests/deadlines_port.rs` | create | port behaviour |
| `crates/kinevo-app/src/modules.rs` | modify | wire ports |

## Interfaces

```rust
#[async_trait] pub trait SchedulableTaskPort<T: Send>: Send + Sync { async fn list_schedulable(&self, tx: &mut T) -> Result<Vec<SchedTaskDto>, AppError>; }
#[async_trait] pub trait DeadlineLookupPort<T: Send>: Send + Sync {
    async fn deadlines(&self, tx: &mut T, goals: &[GoalId], milestones: &[MilestoneId]) -> Result<DeadlineMap, AppError>;
}
pub fn assemble(ctx: AssembleCtx) -> Assembled;
```

## Business rules

1. Tasks of paused/achieved/abandoned goals are excluded; tasks with no goal are included.
2. Local-date deadlines become the start of the next local day in the account timezone.
3. The policy snapshot lists every parameter that influenced the input, including the timezone id.
4. The same function builds the input for drafts and for `GET /schedule/input` (one source of truth).

## Security

- Ports return DTOs, never entities; every query runs inside the caller's account `Tx`.
- SEC-AC-19 groundwork: no query takes an account id parameter.

## Implementation notes

- Add `Cargo` edges only through `kinevo-ports`; the scheduler crate does not depend on `kinevo-mod-tasks`/`goals` (CA-03).
- Use the golden days of a UTC+7 account, a New York spring-forward day and a Berlin fall-back day in the tests.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** tasks in assorted statuses and goal states **When** assembled **Then** only eligible ones appear and `in_progress` slots become `fixed` | `assemble::sch_fr_06_eligibility` |
| AC-2 | **Given** no estimate **When** assembled **Then** `scheduler.default_task_minutes`; the anchor uses the anchor duration | `assemble::sch_fr_06_duration_defaults` |
| AC-3 | **Given** a goal `target_date` in a UTC+7 account **When** assembled **Then** the deadline is the start of the next local day as an instant | `assemble::sch_fr_06_deadline_next_local_day` |
| AC-4 | **Given** DST transition days **When** day windows are built **Then** nonexistent times move forward and ambiguous ones take the earlier instant | `assemble::d_33_dst_day_windows` |
| AC-5 | **Given** an injected clock mid-slot **When** the horizon is built **Then** it starts at `now` rounded up to the slot boundary | `assemble::sch_fr_16_horizon_clamped_to_now` |
| AC-6 | **Given** tasks of two workspaces **When** assembled **Then** all compete in one input | `assemble::sch_fr_15_account_wide` |
| AC-7 | **Given** current assignments of which one became infeasible **When** a reschedule input is built **Then** the feasible one is `fixed` and the infeasible task has `current_slot` | `reschedule::sch_2_8_fixed_and_current_slot` |
| AC-8 | **Given** the ports **When** called by another account **Then** only own rows appear | `schedulable_port::sec_ac_19_ports_return_own_rows` |
| AC-9 | **Given** one input **When** hashed twice **Then** identical `input_hash`, and any field change alters it | `assemble::sch_fr_05_input_hash_stable` |

## Tests

```
cargo nextest run -p kinevo-mod-scheduler --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
cargo nextest run -p kinevo-mod-tasks -p kinevo-mod-goals --features kinevo-db/testing
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
