---
id: T-200
title: Create the execution module: migration, descriptor and the pure focus-session state machine
milestone: M4
kind: feature
status: todo
depends_on: [T-164]
requirements: [EXE-FR-04, EXE-FR-05, EXE-FR-06, EXE-FR-07, DOM-20, D-44, SEC-AC-01]
branch: m4-t200-execution-schema-and-session-domain
budget_lines: 360
assignee: ""
---

# T-200: Create the execution module: migration, descriptor and the pure focus-session state machine

## Goal

The execution crate owns `focus_sessions` with its constraints and a pure session state machine covering start, complete, abandon and stale handling.

## Context

> **Module** (`spec/modules/execution.md`): kind core, depends on `tasks` and `scheduler`; table `focus_sessions`; permissions `execution.today.read`, `execution.session.read|start|complete|abandon` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §7): `focus_sessions(id, account_id, task_id → tasks CASCADE, assignment_id → schedule_assignments SET NULL, state in (running, completed, abandoned) as focus_state_ck, started_at, ended_at, target_minutes 1–1440, outcome in (done, partial), note, version, CHECK ((state='running') = (ended_at IS NULL)), CHECK (ended_at IS NULL OR ended_at >= started_at))`; unique partial `focus_one_running (account_id) WHERE state='running'`; index `focus_by_task (account_id, task_id, started_at DESC)`; standard RLS. Migration: `crates/kinevo-mod-execution/migrations/20270201000000_execution_execution.sql` (D-44; M4 range `2027020…`).
> **State machine** (§5): (none) → `running` on start (no running session, task startable); `running` → `completed` on complete with `started_at ≤ ended_at ≤ now`; `running` → `abandoned`; terminal states reject any event with `session_not_running`. **EXE-FR-07**: a running session older than `execution.session.stale_after_minutes` is `stale=true`; completing a stale session without `ended_at` uses `started_at + target_minutes`, never `now`. **EXE-FR-05**: the client `ended_at` must satisfy `started_at ≤ ended_at ≤ server_now` else `ended_at_invalid`. Validation: `target_minutes` 1–1440, `note` long text ≤ 2 000, `outcome` enum.
> **Constraint mapping**: `focus_one_running` → `session_already_running` (with `session_id` and `task_id` details looked up by the use case).

## Scope

**In:**
- Crate skeleton, descriptor (`requires: ["tasks","scheduler"]`, permissions, emits `SessionStarted`, `SessionCompleted`, `SessionAbandoned`, consumes `AssignmentsApplied`, `TaskStatusChanged`), migration, grants, `domain/session.rs` (`Session`, `start`, `complete`, `abandon`, `is_stale`), fixtures, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Endpoints and the task transitions: T-201.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-execution/**` | create | skeleton |
| `crates/kinevo-mod-execution/migrations/20270201000000_execution_execution.sql` | create |  |
| `crates/kinevo-mod-execution/tests/{isolation,descriptor,session}.rs` | create | AC-1…AC-8 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
impl Session {
    pub fn start(id: SessionId, task: TaskId, assignment: Option<AssignmentId>, target: Minutes, now: Instant) -> Self;
    pub fn complete(self, outcome: Outcome, note: Option<Note>, ended_at: Option<Instant>, now: Instant, stale_after: Minutes) -> Result<Completed, SessionError>;
    pub fn abandon(self, now: Instant) -> Result<Self, SessionError>;
    pub fn is_stale(&self, now: Instant, stale_after: Minutes) -> bool;
}
```

## Business rules

1. The domain never reads the clock; `now` is an argument.
2. A completed session keeps `ended_at` and `outcome`; an abandoned one has neither outcome nor note.
3. Whole minutes only: durations use integer arithmetic.

## Security

- `run_isolation` covers `focus_sessions`.
- The note is S2 data: never logged.

## Implementation notes

- `SessionError` maps to `session_not_running` and `ended_at_invalid` in T-201.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including constraint names | `isolation::dom_focus_sessions_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean | `isolation::sec_ac_01_execution_isolated` |
| AC-3 | **Given** two running sessions in one account **When** inserted **Then** `focus_one_running` rejects the second | `isolation::one_running_per_account` |
| AC-4 | **Given** a running session **When** completed with an `ended_at` before start or after now **Then** `EndedAtInvalid` | `session::sec_ac_22_ended_at_bounds` |
| AC-5 | **Given** a stale session **When** completed without `ended_at` **Then** `ended_at = started_at + target_minutes` | `session::exe_fr_07_stale_completion_uses_target` |
| AC-6 | **Given** a completed or abandoned session **When** any event arrives **Then** `NotRunning` | `session::terminal_states_reject_events` |
| AC-7 | **Given** abandon **When** applied **Then** no outcome and no note are stored | `session::exe_fr_06_abandon_has_no_outcome` |
| AC-8 | **Given** the descriptor **When** validated with the others **Then** the graph and registries pass | `descriptor::mod_08_execution_descriptor_valid` |

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
