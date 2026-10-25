---
id: T-150
title: Create the landscape module: migration, descriptor and fixtures
milestone: M3
kind: feature
status: todo
depends_on: [T-112]
requirements: [LAND-FR-05, DOM-20, D-44, SEC-AC-01]
branch: m3-t150-landscape-schema-and-descriptor
budget_lines: 300
assignee: ""
---

# T-150: Create the landscape module: migration, descriptor and fixtures

## Goal

The `landscape` crate has its three tables with every constraint of the DDL, RLS, descriptor and isolation tests.

## Context

> **Module** (`spec/modules/landscape.md`): kind core, depends on `identity`; tables `landscape_events`, `schedule_overrides`, `import_batches`. Permissions `landscape.event.read|create|update|delete`, `landscape.override.read|create|delete`, `landscape.import.create|delete`, `landscape.export.read` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §6): `import_batches(id, account_id → accounts CASCADE, source in ('ics'), filename, event_count, created_at)`; `landscape_events(id, account_id, title 1–200, kind in (one_time, recurring) as landscape_kind_ck, start_at, end_at, timezone text, rrule jsonb, source in (manual, ics), external_uid, import_batch_id → import_batches ON DELETE SET NULL, timestamps, version, CHECK end_at > start_at, CHECK ((kind='recurring') = (rrule IS NOT NULL)))` with unique partial index `landscape_ics_uid (account_id, external_uid) WHERE external_uid IS NOT NULL`; `schedule_overrides(id, account_id, event_id → landscape_events CASCADE, kind in (permanent, one_time) as override_kind_ck, effective_from date, effective_to date, override_start_at, override_end_at, cancels_occurrence boolean, reason, created_at, CHECK override_end_at > override_start_at, CHECK effective_to >= effective_from)`. Standard RLS on all three. **LAND-FR-05**: landscape events never carry a workspace (no column).
> **Migration** (D-44): `crates/kinevo-mod-landscape/migrations/20270101000000_landscape_landscape.sql` (M3 modules use the `2027…` range: landscape `20270101000000`, scheduler `20270101000100`).
> **Constraint mapping**: `landscape_ics_uid` → internal (the import use case resolves duplicates before insert and reports `status="exists"`); CHECKs are invariants.

## Scope

**In:**
- Crate skeleton, descriptor (`requires: ["identity"]`, nine permissions, events `LandscapeChanged`, `LandscapeImported`), migration, grants, fixtures, wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Behaviour: T-151 onward. Nav item and UI: T-160.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-landscape/**` | create | skeleton |
| `crates/kinevo-mod-landscape/migrations/20270101000000_landscape_landscape.sql` | create |  |
| `crates/kinevo-mod-landscape/tests/{isolation,descriptor}.rs` | create | AC-1…AC-5 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "landscape", kind: ModuleKind::Core, requires: &["identity"], .. };
```

## Business rules

1. Overrides cascade with their event; deleting an import batch row leaves events (`SET NULL`), the undo use case deletes them explicitly (T-154).
2. The DB enforces `kind` matches `rrule` presence.

## Security

- `run_isolation` covers all three tables.
- An override cannot reference another account's event (FK invisible under RLS).

## Implementation notes

- `schedule_overrides` has no `updated_at`/`version` in the DDL: overrides are created and deleted, never edited.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal including constraint names | `isolation::dom_landscape_schema_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean for the three tables | `isolation::sec_ac_01_landscape_isolated` |
| AC-3 | **Given** `kind='recurring'` with null `rrule` **When** inserted **Then** the CHECK rejects it | `isolation::kind_matches_rrule_presence` |
| AC-4 | **Given** an override for another account's event **When** inserted **Then** it fails | `isolation::cross_account_override_rejected` |
| AC-5 | **Given** the descriptor **When** validated with the others **Then** the graph and registries pass | `descriptor::mod_08_landscape_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-landscape --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
