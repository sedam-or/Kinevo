---
id: T-106
title: Implement the pure task domain: state machine, progress and consistency rules
milestone: M2
kind: feature
status: todo
depends_on: [T-105]
requirements: [TASK-FR-03, TASK-FR-04, TASK-FR-05, TASK-FR-06, CA-01, CA-16, D-36]
branch: m2-t106-tasks-domain-state-machine
budget_lines: 380
assignee: ""
---

# T-106: Implement the pure task domain: state machine, progress and consistency rules

## Goal

All task business rules that need no database are one pure, exhaustively tested module: the status machine with actors, `allowed_transitions`, progress percentage and anchor rules.

## Context

> **State machine** (`spec/modules/tasks.md` §4). Actors: U user, E execution, S scheduler, J job; ✓U = also directly requestable by the user. Rows: `backlog→scheduled` (S, or U via the scheduler API; needs a current assignment); `backlog→in_progress` (E); `backlog→completed` (U ✓, sets `completed_at`); `backlog→skipped` (U ✓); `scheduled→in_progress` (E); `scheduled→missed` (J); `scheduled→conflict` (S); `scheduled→skipped` (U ✓); `scheduled→backlog` (U ✓ or S, unschedule); `in_progress→completed` (E or U ✓); `in_progress→partial` (E); `in_progress→conflict` (S); `in_progress→skipped` (U ✓); `partial→continued` (U ✓ or S); `partial→completed` (U ✓); `partial→scheduled` (S); `partial→skipped` (U ✓); `continued→scheduled` (S) and `continued→in_progress` (E); `continued→completed` and `continued→skipped` (U ✓); `missed→backlog` (U ✓); `missed→scheduled` (S); `missed→completed` (U ✓); `conflict→scheduled` (S); `conflict→in_progress` (E); `conflict→backlog` (U ✓); `completed|skipped→backlog` (U ✓, reopen, D-36: clears `completed_at`, anchor guard applies); any same-state request is a no-op (200, version unchanged); everything else → 409 `invalid_transition`.
> **TASK-FR-04**: transitions are applied only through `POST /tasks/{id}/status` or by system actors; the `to` accepted from the user is restricted to the ✓U rows. **TASK-FR-05**: `progress_pct` with subtasks is `(200*done + total) / (2*total)` with integer division (round half up); without subtasks it is 0 until `completed`, then 100; recomputed in the same transaction as every subtask or status change. **TASK-FR-06**: completing with open subtasks marks no subtask; completing sets `completed_at` from the injected clock; reopening clears it. **TASK-FR-03**: at most one non-terminal anchor per account; an anchor's `estimate_minutes` is null or equals `scheduler.anchor.duration_minutes` (the policy arrives in M3; until then the domain receives the allowed duration as an argument and the use case passes `None`, meaning "any value allowed").
> **SM-R6 / SM-T2**: every task representation includes `allowed_transitions` for the current state (user view: the ✓U targets).
> **CA-01/CA-16**: business decisions live in pure domain code; no async, no database, no clock reads (the instant is a parameter).

## Scope

**In:**
- `domain/status.rs`: `TaskStatus`, `Actor`, `transition(from, to, actor, ctx) -> Result<Outcome, InvalidTransition>` with `Outcome { NoOp, Changed { completed_at: SetClear } }`, `user_targets(from) -> Vec<TaskStatus>`.
- `domain/progress.rs`: `progress_pct(done, total, completed) -> u8`.
- `domain/anchor.rs`: `check_anchor(is_anchor, estimate, allowed_duration)`.
- `domain/consistency.rs`: `check_links(task_ws, goal: Option<GoalInfo>, milestone: Option<MilestoneInfo>)` returning the three registry errors (used before the DB constraint to give precise messages).
- Exhaustive table tests and a property test for progress.

**Out:**
- Persistence and HTTP: T-107/T-108.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-tasks/src/domain/{status,progress,anchor,consistency}.rs` | create | pure code |
| `crates/kinevo-mod-tasks/tests/{status_table,progress_props}.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub fn transition(from: TaskStatus, to: TaskStatus, actor: Actor, now: Instant) -> Result<Outcome, InvalidTransition>;
pub fn user_targets(from: TaskStatus) -> &'static [TaskStatus];
pub fn progress_pct(done: u32, total: u32, completed: bool) -> u8;
```

## Business rules

1. The transition table above is the single source; a test generates all 9×9 pairs × 4 actors and compares with an explicit expectation table in the test file.
2. Reopening sets `completed_at` to cleared; completing sets it to `now`.
3. Progress is monotone in `done` and within 0..=100 for all inputs with `done <= total`.
4. No function in this module reads time or randomness.

## Security

- Actor is an explicit argument; the HTTP layer can only construct `Actor::User` (compile-time separation by constructors visible to the execution/scheduler modules through a ports-level token in later milestones).

## Implementation notes

- Keep the table as a `const` array of `(from, to, actors, user_allowed)` and derive both `transition` and `user_targets` from it, so the web `allowed_transitions` cannot disagree with the machine.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** every (from, to, actor) triple **When** evaluated **Then** results equal the spec table exactly | `status_table::task_fr_04_full_transition_table` |
| AC-2 | **Given** user actors **When** requesting non-✓U targets **Then** `invalid_transition` | `status_table::task_fr_04_user_restricted_targets` |
| AC-3 | **Given** same-state requests **When** evaluated **Then** `NoOp` for every state | `status_table::any_same_state_is_noop` |
| AC-4 | **Given** completed or skipped **When** reopened **Then** target backlog with `completed_at` cleared | `status_table::d_36_reopen_clears_completed_at` |
| AC-5 | **Given** subtasks totals 0..50 **When** computing progress **Then** values follow the formula, stay in 0..100 and are monotone in `done` | `progress_props::task_fr_05_progress_formula_and_bounds` |
| AC-6 | **Given** no subtasks **When** not completed vs completed **Then** 0 vs 100 | `progress_props::task_fr_05_no_subtasks_zero_or_hundred` |
| AC-7 | **Given** an anchor with estimate different from the allowed duration **When** checked **Then** it fails; null passes | `status_table::task_fr_03_anchor_estimate_rule` |
| AC-8 | **Given** goal in another workspace, milestone in another goal and milestone without goal **When** checked **Then** the three precise errors | `status_table::task_fr_01_link_errors` |

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
