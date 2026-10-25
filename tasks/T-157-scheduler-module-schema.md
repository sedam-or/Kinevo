---
id: T-157
title: Create the scheduler module: migration, descriptor, provisioner and database guarantees
milestone: M3
kind: feature
status: todo
depends_on: [T-153, T-156, T-108]
requirements: [SCH-FR-08, SCH-FR-13, DOM-20, D-44, D-41, SEC-AC-01, TST-06]
branch: m3-t157-scheduler-module-schema
budget_lines: 340
assignee: ""
---

# T-157: Create the scheduler module: migration, descriptor, provisioner and database guarantees

## Goal

The scheduler module owns its four tables with the no-overlap exclusion constraint proven by a test, and every new account gets its schedule state row.

## Context

> **Module** (`spec/modules/scheduler.md`): kind core, depends on `tasks` and `landscape`; tables `schedule_states`, `schedule_assignments`, `schedule_drafts`, `scheduler_runs`. Permissions: `scheduler.schedule.read`, `scheduler.draft.create|apply|dismiss`, `scheduler.assignment.place|lock|remove`, `scheduler.export.read` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §6): `schedule_states(account_id PK, version bigint 0, review_state in (clean, needs_review), review_reason, updated_at)`; `schedule_assignments(id, account_id, task_id → tasks CASCADE, start_at, end_at, source in (auto, manual), locked, state in (current, superseded) as assignment_state_ck, schedule_version, superseded_at, superseded_by_draft, created_at, CHECK end_at > start_at, EXCLUDE USING gist (account_id WITH =, tstzrange(start_at,end_at,'[)') WITH &&) WHERE state='current' as assignments_no_overlap)`, unique partial `assignments_one_current_per_task (task_id) WHERE state='current'`; `schedule_drafts(id, account_id, kind in (weekly, manual, sync), horizon_start, horizon_end, base_version, status in (pending, applied, dismissed, stale) as draft_status_ck, algorithm_version, input_hash bytea, policy_snapshot jsonb, result jsonb, created_at, decided_at, CHECK horizon_end > horizon_start)` with unique partial `drafts_one_pending_weekly (account_id, horizon_start) WHERE kind='weekly' AND status='pending'`; `scheduler_runs(id, account_id, trigger in (user, weekly, sync), status in (running, succeeded, failed), lease_until, draft_id → schedule_drafts SET NULL, error_code, started_at, finished_at)` with unique partial `scheduler_one_running (account_id) WHERE status='running'`. Standard RLS on all four.
> **Migration** (D-44): `crates/kinevo-mod-scheduler/migrations/20270101000100_scheduler_scheduler.sql`. `btree_gist` already exists (T-009). **Provisioning** (D-41): the module registers an `AccountProvisioner` that inserts the `schedule_states` row (version 0, clean) in the account-creation transaction; a migration backfill inserts missing rows for existing accounts.
> **Constraint mapping**: `assignments_no_overlap` → `schedule_version_conflict`? No: it is an invariant that normal flows never trigger because apply re-validates; if it fires it maps to `draft_infeasible` for apply and `placement_invalid` for manual placement (the use cases map it explicitly). `scheduler_one_running` → `scheduler_busy`. `drafts_one_pending_weekly` → internal (the job uses `ON CONFLICT DO NOTHING`).
> **The database guarantee** (`06-domain-model.md` §6 note): a test proves the exclusion constraint rejects overlapping current assignments, because a legacy document claimed such a guarantee the code did not provide.

## Scope

**In:**
- Crate skeleton, descriptor (`requires: ["tasks","landscape"]`, permissions, emits `DraftCreated`, `AssignmentsApplied`, `AssignmentSuperseded`, `AssignmentLockChanged`, `ScheduleNeedsReview`; subscribes to the consumed events of §8; jobs `scheduler.weekly_draft`, `scheduler.lease.sweep`, `scheduler.drafts.prune`), migration, grants, provisioner registration, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Behaviour: T-158 onward.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-scheduler/**` | create | skeleton |
| `crates/kinevo-mod-scheduler/migrations/20270101000100_scheduler_scheduler.sql` | create |  |
| `crates/kinevo-mod-scheduler/tests/{isolation,descriptor,constraints}.rs` | create | AC-1…AC-7 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "scheduler", kind: ModuleKind::Core, requires: &["tasks","landscape"], .. };
```

## Business rules

1. A task's current assignment disappears with the task (CASCADE); history rows of superseded assignments cascade the same way.
2. The provisioner is idempotent (`ON CONFLICT DO NOTHING`).

## Security

- `run_isolation` covers the four tables.
- The exclusion constraint includes `account_id` so accounts never block each other.

## Implementation notes

- The descriptor's job names are registered but the handlers arrive in T-161; a registered job without a handler must make the architecture test fail, so register the jobs in T-161, not here.
- `superseded_by_draft` has no FK in the DDL; keep it that way.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including constraint and index names | `isolation::dom_scheduler_schema_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for the four tables | `isolation::sec_ac_01_scheduler_isolated` |
| AC-3 | **Given** two current assignments overlapping in one account **When** inserted **Then** `assignments_no_overlap` rejects; different accounts do not conflict; a superseded one is ignored | `constraints::sch_r7_overlap_rejected_by_database` |
| AC-4 | **Given** a second current assignment for one task **When** inserted **Then** the unique index rejects it | `constraints::one_current_assignment_per_task` |
| AC-5 | **Given** a second pending weekly draft for the same horizon **When** inserted **Then** rejected; a different kind is allowed | `constraints::drafts_one_pending_weekly` |
| AC-6 | **Given** a new account **When** provisioned **Then** a `schedule_states` row with version 0 and `clean` exists; the backfill adds missing rows | `descriptor::provisioner_creates_state_row` |
| AC-7 | **Given** the descriptor **When** validated with the others **Then** the graph, permissions and event registries pass | `descriptor::mod_08_scheduler_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-scheduler --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
