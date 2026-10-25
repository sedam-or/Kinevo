---
id: T-105
title: Create the tasks module: migration, descriptor and policies
milestone: M2
kind: feature
status: todo
depends_on: [T-102]
requirements: [TASK-FR-01, TASK-FR-02, TASK-FR-03, DOM-20, D-44, POL-01, SEC-AC-01]
branch: m2-t105-tasks-schema-and-descriptor
budget_lines: 320
assignee: ""
---

# T-105: Create the tasks module: migration, descriptor and policies

## Goal

The `tasks` crate has the `tasks` and `subtasks` tables with every constraint of the DDL, its descriptor and the four task policies in the registry.

## Context

> **Module** (`spec/modules/tasks.md`): kind core, depends on `goals`; tables `tasks`, `subtasks`. Permissions `tasks.task.read|create|update|delete|status`, `tasks.subtask.read|create|update|delete` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §5): `tasks(id, account_id → accounts CASCADE, workspace_id → workspaces, goal_id, milestone_id, title 1–500, notes default '', status in (backlog, scheduled, in_progress, partial, continued, completed, skipped, missed, conflict) as task_status_ck, priority_tier smallint 1–3, estimate_minutes int null 1–1440, due_at timestamptz, is_anchor boolean, progress_pct smallint 0–100, completed_at, created_at, updated_at, version)` with `task_milestone_needs_goal CHECK`, composite FKs `task_goal_same_workspace (workspace_id, goal_id) → goals (workspace_id, id)` and `task_milestone_in_goal (goal_id, milestone_id) → milestones (goal_id, id)`, and `task_completed_at_ck CHECK ((status = 'completed') = (completed_at IS NOT NULL))`; indexes `tasks_one_open_anchor UNIQUE (account_id) WHERE is_anchor AND status NOT IN ('completed','skipped')`, `tasks_open`, `tasks_by_goal`; `subtasks(id, account_id, task_id → tasks CASCADE, title 1–500, completed, position, timestamps, version)`. Standard RLS on both.
> **Constraint mapping** to registry codes: `task_goal_same_workspace` → `task_goal_workspace_mismatch`; `task_milestone_in_goal` → `task_milestone_goal_mismatch`; `task_milestone_needs_goal` → `milestone_requires_goal`; `tasks_one_open_anchor` → `anchor_exists` (the use case adds `details.task_id` after lookup).
> **Policies** (`04-policies.md`): `tasks.default_priority_tier` (int 1–3, default 2, instance and account scope), `tasks.max_subtasks` (1–200, default 50), `tasks.title_max_chars` (40–500, default 200); add to the kernel registry and descriptor.
> **Migration** (D-44): `crates/kinevo-mod-tasks/migrations/20261201000200_tasks_tasks_subtasks.sql`.
> **Job declared now, implemented in M4**: `tasks.mark_missed` is listed in the descriptor in M4 with the execution module; do not declare it here.

## Scope

**In:**
- Crate skeleton, descriptor (`requires: ["goals"]`), migration, grants, constraint mapping, fixtures, kernel registry entries and sync test update; Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Behaviour: T-106…T-108.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-tasks/**` | create | skeleton |
| `crates/kinevo-mod-tasks/migrations/20261201000200_tasks_tasks_subtasks.sql` | create |  |
| `crates/kinevo-mod-tasks/src/infra/constraints.rs` | create | mapping |
| `crates/kinevo-kernel/src/policy/registry.rs` | modify | three keys |
| `crates/kinevo-mod-tasks/tests/{isolation,constraints,descriptor}.rs` | create | AC-1…AC-7 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "tasks", kind: ModuleKind::Core, requires: &["goals"],
    policy_keys: &["tasks.default_priority_tier", "tasks.max_subtasks", "tasks.title_max_chars"], .. };
```

## Business rules

1. A task's goal must be in the same workspace and its milestone in that goal; the database enforces it (DOM-20).
2. At most one open anchor per account.
3. `completed_at` is set exactly when status is `completed`.

## Security

- `run_isolation` covers both tables.
- Cross-account goal ids fail on insert (FK invisible under RLS).

## Implementation notes

- Keep CHECK and FK names exactly as in the DDL; the constraint mapping depends on them.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including constraint names | `isolation::dom_tasks_schema_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for both tables | `isolation::sec_ac_01_tasks_subtasks_isolated` |
| AC-3 | **Given** a task with goal in another workspace **When** inserted **Then** the composite FK rejects it and maps to `task_goal_workspace_mismatch` | `constraints::task_fr_01_goal_workspace_constraint` |
| AC-4 | **Given** a milestone of another goal **When** inserted **Then** it maps to `task_milestone_goal_mismatch`; a milestone without goal maps to `milestone_requires_goal` | `constraints::task_fr_01_milestone_constraints` |
| AC-5 | **Given** two open anchors **When** the second is inserted **Then** it maps to `anchor_exists`; after completing the first it succeeds | `constraints::task_fr_03_one_open_anchor` |
| AC-6 | **Given** `status=completed` with null `completed_at` **When** inserted **Then** the CHECK rejects it | `constraints::completed_at_matches_status` |
| AC-7 | **Given** the registry **When** compared with the descriptor **Then** the three keys exist with the spec bounds and defaults | `descriptor::mod_08_tasks_descriptor_valid` |

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
