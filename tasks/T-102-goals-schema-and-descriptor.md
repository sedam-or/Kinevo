---
id: T-102
title: Create the goals module: migration, descriptor and policies
milestone: M2
kind: feature
status: todo
depends_on: [T-100, T-033]
requirements: [GOAL-FR-01, GOAL-FR-07, DOM-20, D-44, POL-01, SEC-AC-01]
branch: m2-t102-goals-schema-and-descriptor
budget_lines: 300
assignee: ""
---

# T-102: Create the goals module: migration, descriptor and policies

## Goal

The `goals` crate has its tables for goals and milestones, RLS, descriptor and the `goals.title_max_chars` policy.

## Context

> **Module** (`spec/modules/goals.md`): kind core, depends on `workspaces`; tables `goals`, `milestones`. Permissions `goals.goal.read|create|update|delete` and `goals.milestone.read|create|update|delete` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §5): `goals(id, account_id → accounts CASCADE, workspace_id → workspaces, title 1–500, description default '', status in ('active','paused','achieved','abandoned') as goal_status_ck, target_date date, created_at, updated_at, version, UNIQUE (workspace_id, id))` with index `goals_by_ws (account_id, workspace_id, status)`; `milestones(id, account_id → accounts CASCADE, goal_id → goals CASCADE, title 1–500, description, status in ('open','done') as milestone_status_ck, target_date, position int, timestamps, version, UNIQUE (goal_id, id))`. Standard `tenant_isolation` RLS on both.
> **Policy** (`04-policies.md`): `goals.title_max_chars` int 40–500, default 200, instance scope; it must be added to the kernel registry (T-006 file) and to the descriptor's `policy_keys`.
> **Migration** (D-44): `crates/kinevo-mod-goals/migrations/20261201000100_goals_goals_milestones.sql` (after workspaces).
> **Constraint mapping**: `goal_status_ck`, `milestone_status_ck` are invariants (internal errors); no user-visible unique constraint exists.

## Scope

**In:**
- Crate skeleton, descriptor (`requires: ["workspaces"]`, 8 permissions, policy key, events), migration, grants, fixtures, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.
- Kernel registry entry for `goals.title_max_chars` with bounds and default, plus the registry sync test update (POL-01).

**Out:**
- Behaviour: T-103/T-104.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-goals/**` | create | skeleton |
| `crates/kinevo-mod-goals/migrations/20261201000100_goals_goals_milestones.sql` | create |  |
| `crates/kinevo-kernel/src/policy/registry.rs` | modify | add the key |
| `crates/kinevo-mod-goals/tests/{isolation,descriptor}.rs` | create | AC-1…AC-5 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "goals", kind: ModuleKind::Core, requires: &["workspaces"], policy_keys: &["goals.title_max_chars"], .. };
```

## Business rules

1. A goal's workspace cannot change after creation (no update path exists; GOAL-FR-01).
2. Milestone `position` defaults to 0 and is meaningful only within a goal.
3. Deleting a goal cascades milestones (FK) but task detachment is done by the use case first (T-103).

## Security

- `run_isolation` covers both tables.
- A test proves a milestone cannot reference another account's goal (FK plus RLS).

## Implementation notes

- The cross-account FK check relies on composite uniqueness only where the DDL provides it; account ownership of referenced rows is enforced by RLS visibility during the insert (the referenced row is invisible, so the FK check fails).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal | `isolation::dom_goals_schema_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for both tables | `isolation::sec_ac_01_goals_milestones_isolated` |
| AC-3 | **Given** account A **When** inserting a milestone for B's goal **Then** it fails | `isolation::cross_account_milestone_rejected` |
| AC-4 | **Given** the registry **When** compared with the descriptor **Then** `goals.title_max_chars` exists with bounds 40–500 and default 200 | `descriptor::mod_08_goals_descriptor_valid` |
| AC-5 | **Given** the dependency graph **When** validated with M1 and M2 descriptors **Then** it passes | `descriptor::validate_graph_with_workspaces` |

## Tests

```
cargo nextest run -p kinevo-mod-goals --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
