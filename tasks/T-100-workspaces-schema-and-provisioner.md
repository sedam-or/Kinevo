---
id: T-100
title: Create the workspaces module: migration, descriptor and the default-workspace provisioner
milestone: M2
kind: feature
status: todo
depends_on: [T-058]
requirements: [WS-FR-01, WS-FR-02, DOM-25, MOD-10, D-44, CA-02, SEC-AC-01]
branch: m2-t100-workspaces-schema-and-provisioner
budget_lines: 330
assignee: ""
---

# T-100: Create the workspaces module: migration, descriptor and the default-workspace provisioner

## Goal

The `workspaces` crate exists with its table, RLS policy, descriptor and an `AccountProvisioner` that gives every new account exactly one default workspace.

## Context

> **Module** (`spec/modules/workspaces.md`): kind core, depends on `identity`; table `workspaces`. Permissions `workspaces.workspace.read|create|update|archive|delete` held by `account_owner` and `instance_admin` (own data).
> **DDL** (`06-domain-model.md` §5): `workspaces(id uuid PK, account_id → accounts ON DELETE CASCADE, name citext 1–80, color enum text, position int default 0, is_default boolean, archived_at, created_at, updated_at, version)`; unique partial index `workspaces_one_default (account_id) WHERE is_default`; unique partial index `workspaces_name_active (account_id, name) WHERE archived_at IS NULL`. RLS: the standard `tenant_isolation` policy enabled and forced; `kinevo_app` has SELECT, INSERT, UPDATE, DELETE.
> **WS-FR-01**: every account has exactly one default workspace created by this module's `AccountProvisioner` (MOD-10); name from the message key `workspace.default_name` in the account's locale; it cannot be archived or deleted; it may be renamed.
> **MOD-10**: provisioners run inside the account-creation transaction in module dependency order, for every compiled module regardless of the enabled flag; a failure rolls back the account.
> **Migration** (D-44): `crates/kinevo-mod-workspaces/migrations/20261201000000_workspaces_workspaces.sql`.
> **Catalogue**: `workspace.default_name` exists in `en.json`/`id.json` of the web app, but the server needs the localised name at creation: the provisioner reads it from a small compiled table `{en: "Personal", id: "Pribadi"}` held in the domain layer as message data (not a business value), and a test checks it equals the web catalogue entries (T-047 catalogue).

## Scope

**In:**
- Crate skeleton like the M1 modules (`domain`, `app`, `ports.rs`, `infra`, `http`), `MODULE` descriptor (permissions above, `requires: ["identity"]`, nav item `workspaces` is not added: the UI lives under Plan, T-109), `migrations()`, stubs for `router()`, `jobs()`, `subscribers()`, `exporters()`.
- The migration with table, indexes, RLS and grants; `CONSTRAINTS` mapping for `workspaces_name_active` → `workspace_name_taken` and `workspaces_one_default` → `internal_error` (an invariant, never user-visible).
- `DefaultWorkspaceProvisioner` implementing `AccountProvisioner<Tx>`; isolation fixtures; Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Endpoints and events: T-101. Entitlement port: T-101.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-workspaces/Cargo.toml, src/**` | create | skeleton |
| `crates/kinevo-mod-workspaces/migrations/20261201000000_workspaces_workspaces.sql` | create | DDL |
| `crates/kinevo-mod-workspaces/src/infra/provisioner.rs` | create |  |
| `crates/kinevo-mod-workspaces/tests/{isolation,provisioner,descriptor}.rs` | create | AC-1…AC-6 |
| `Cargo.toml, crates/kinevo-app/Cargo.toml, crates/kinevo-app/src/modules.rs` | modify | wiring |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "workspaces", kind: ModuleKind::Core, requires: &["identity"], /* 5 permissions */ .. };
pub fn provisioners() -> Vec<Arc<dyn AccountProvisioner<Tx>>>;
```

## Business rules

1. Provisioning inserts exactly one row with `is_default = true`, `position = 0`, `color = NULL`.
2. The default name uses the account locale; unknown locales fall back to `en`.
3. Re-running the provisioner for an account that already has a default is a no-op error `internal_error` (never reached in normal flow; covered by the unique index).

## Security

- `run_isolation` covers `workspaces` (SEC-AC-01).
- The provisioner uses the passed `Tx` only; it never opens a connection.

## Implementation notes

- Message data for the default name: constants `DEFAULT_NAME_EN`/`DEFAULT_NAME_ID` in `domain/names.rs` with a comment; the sync test compares to `web/src/i18n/*.json`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** the table metadata is compared with the spec DDL **Then** equal | `isolation::dom_workspaces_schema_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs on `workspaces` **Then** clean | `isolation::sec_ac_01_workspaces_isolated` |
| AC-3 | **Given** a new account **When** provisioned **Then** exactly one default workspace named for its locale | `provisioner::ws_fr_01_one_default_per_account` |
| AC-4 | **Given** an account with a default **When** a second default is inserted **Then** the unique index rejects it | `provisioner::ws_fr_01_unique_default` |
| AC-5 | **Given** a failing provisioner after this one **When** registering **Then** no account row remains (MOD-10) | `provisioner::mod_10_rolls_back_with_account` |
| AC-6 | **Given** the compiled name table **When** compared with the web catalogue **Then** equal | `provisioner::default_name_matches_catalogue` |

## Tests

```
cargo nextest run -p kinevo-mod-workspaces --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
