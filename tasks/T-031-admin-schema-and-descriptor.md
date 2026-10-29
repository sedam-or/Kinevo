---
id: T-031
title: Create the admin schema migration, module skeleton and descriptor
milestone: M1
kind: feature
status: in_progress
depends_on: [T-012, T-020]
requirements: [D-39, D-40, D-41, D-44, D-46, DOM-10, DOM-26, ARCH-23, CA-02, CA-04, SEC-AC-01, SEC-AC-02, MOD-08]
branch: m1-t031-admin-schema
budget_lines: 360
assignee: ""
---

# T-031: Create the admin schema migration, module skeleton and descriptor

## Goal

The `admin` crate has its layered skeleton, its descriptor and the migration that creates `instance_settings`, `instance_modules`, `policy_versions` and `secrets` with the exact grants and policies of the spec. The isolation harness covers the tenant-scoped parts.

## Context

> **Ownership** (`06` §3, D-41): `instance_settings`, `instance_modules`, `policy_versions`, `secrets` → `admin`. `audit_log`, outbox, jobs, `idempotency_keys` are platform tables (T-012) and are only reached through `kinevo-db`.
> **DDL**: copy exactly the `CREATE TABLE` statements in `06-domain-model.md` §3 for the four tables: `instance_settings` (singleton `id = 1`, `setup_completed`, `setup_token_hash`, `base_url`, `default_locale`, `template_id`, timestamps, `version`), `instance_modules` (`module_key` with the key CHECK, `enabled`, `updated_by → accounts ON DELETE SET NULL`, `version`), `policy_versions` (+ `policy_versions_lookup` index, `policy_scope_account_ck`), `secrets` (`UNIQUE NULLS NOT DISTINCT (account_id, name)`).
> **Policies and grants** (`06` §2.2, ADR-0011, D-46): `instance_settings`, `instance_modules`: no RLS; `kinevo_app` SELECT, INSERT, UPDATE (writes only through repositories that take an admin `Tx`); `kinevo_system` SELECT. `policy_versions`: RLS enabled and forced; `USING (account_id IS NULL OR account_id = <current>)`; `WITH CHECK (account_id = <current> OR (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on'))`; `kinevo_app` SELECT, INSERT only (append-only, DOM-26); `kinevo_system` SELECT. `secrets`: RLS enabled and forced with `tenant_isolation` (standard text) for account rows plus `instance_secrets_read FOR SELECT USING (account_id IS NULL)` and `instance_secrets_write FOR ALL USING/WITH CHECK (account_id IS NULL AND current_setting('app.instance_admin', true) = 'on')`; `kinevo_app` SELECT, INSERT, UPDATE, DELETE; `kinevo_system` SELECT.
> **Singleton seed**: the migration inserts the `instance_settings` row `id = 1` with defaults (`setup_completed = false`) and one `instance_modules` row per **known** module key of the crates compiled so far is **not** inserted by SQL (switches are written by setup/admin); the absence of a row means "default for the template" (see T-034).
> **Migration file** (D-44): `crates/kinevo-mod-admin/migrations/20261101000300_admin_instance.sql`, after the platform tables.
> **Descriptor** (`admin.md` §1 and rbac): permissions — owner and admin: `admin.policies.account.read`, `admin.policies.account.write`, `admin.policies.instance.read`, `admin.instance.read`, `admin.audit.read_own`; admin only: `admin.policies.instance.write`, `admin.modules.manage`, `admin.audit.read`, `admin.jobs.read`, `admin.jobs.retry`, `admin.secrets.manage`. Requires `identity`. Kind core. Jobs: `admin.audit.prune`, `admin.idempotency.prune`, `jobs.prune`, `outbox.prune`, `outbox.dispatch`, `outbox.retry`. Policy keys owned (§3.3/3.5 of `04-policies.md`): `audit.retention_days`, `api.idempotency.ttl`, `modules.cache_ttl_seconds`, `jobs.lease_seconds`, `jobs.retry.max_attempts`, `jobs.retry.base_seconds`, `jobs.retry.cap_seconds`, plus the rate-limit keys `ratelimit.*` (six). *Descriptor ownership of a key does not move its registry entry from T-006; the descriptor lists the keys it consumes for MOD-08.* Emits `InstanceSetupCompleted`, `ModuleSwitched`, `PolicyChanged`, `SecretChanged`.
> **Secret name registry** (`admin.md` §2): instance `smtp.password`, `ai.api_key`, `billing.gateway_key`, `billing.webhook_secret`; account `identity.totp_seed`, `ai.api_key`.
> **DOM-10**: secrets are never plain-text columns; `ciphertext`, `nonce`, `key_id` columns plus `last4` display mask only.

## Scope

**In:**
- Crate skeleton like identity's: `src/{lib.rs, domain, app, ports.rs, infra, http}`, `migrations/`. `lib.rs` exports `MODULE`, `migrations()`, stubs for `router()`, `jobs()`, `subscribers()`, `provisioners()`, `exporters()`.
- The migration with tables, indexes, constraints, RLS, policies and grants.
- `infra::constraints::CONSTRAINTS` mapping for admin tables (e.g. `instance_modules_pkey` conflicts are not user-visible; map none unless needed — document).
- Domain types: `PolicyScope`, `SecretNameRegistered` helper re-exporting `SecretName` from ports.
- Isolation fixtures for `policy_versions` (account rows) and `secrets` (account rows) and the harness no-RLS allow-list entries for `instance_settings` and `instance_modules` (each with a reason string).
- Tests as in Acceptance.

**Out:**
- Repositories and behaviour: T-032 onward.
- The `SecretStore` implementation: T-032. Policies service: T-033.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/Cargo.toml` | modify | deps like identity |
| `crates/kinevo-mod-admin/src/lib.rs` | modify | descriptor, stubs |
| `crates/kinevo-mod-admin/src/{domain,app,infra,http}/mod.rs`, `src/ports.rs` | create | |
| `crates/kinevo-mod-admin/migrations/20261101000300_admin_instance.sql` | create | |
| `crates/kinevo-mod-admin/tests/admin_isolation.rs`, `tests/descriptor.rs`, `tests/support/fixtures.rs` | create | |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "admin", kind: ModuleKind::Core, requires: &["identity"], /* 5 owner + 6 admin-only permissions */ .. };
pub fn migrations() -> MigrationSource;
```

## Business rules

1. `instance_settings` can hold exactly one row (`id = 1` CHECK) and it exists after migration.
2. `policy_versions` is append-only for the application roles (no UPDATE/DELETE grant).
3. Instance policy rows are readable by every account; writing one requires `app.instance_admin = 'on'`.
4. Instance secret rows are readable (ciphertext) by every role and writable only in an admin context; account secrets are visible only to their account.
5. `secrets (account_id, name)` is unique with NULLs equal (one instance secret per name).

## Security

- A test proves that an ordinary `Tx::begin(A)` cannot insert an instance policy row or an instance secret, and that `Tx::begin_admin(A)` can.
- No secret value or plaintext exists in any column; `last4` is the only derived display data.
- `verify_isolation` accepts exactly the allowed policy set including the new `secrets` and `policy_versions` rules.

## Implementation notes

- Policy expressions for `policy_versions` are given verbatim in ADR-0011.
- Seed the singleton with `INSERT … ON CONFLICT DO NOTHING` to keep the migration re-runnable in tests.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** metadata of the four tables is compared to the spec DDL **Then** they match | `admin_isolation::dom_schema_matches_spec_ddl` |
| AC-2 | **Given** the fixtures **When** `run_isolation` runs **Then** clean for `policy_versions` and `secrets` account rows; `instance_settings`, `instance_modules` are allow-listed no-RLS tables | `admin_isolation::sec_ac_01_admin_tables_isolated` |
| AC-3 | **Given** `Tx::begin(A)` **When** inserting an instance policy row (account_id null) **Then** the RLS check fails; with `begin_admin(A)` it succeeds | `admin_isolation::adr_0011_instance_policy_needs_admin_flag` |
| AC-4 | **Given** account A and B policy rows and an instance row **When** A reads **Then** A sees instance + own rows, never B's | `admin_isolation::pol_scope_visibility` |
| AC-5 | **Given** `kinevo_app` **When** it tries `UPDATE` or `DELETE` on `policy_versions` **Then** privilege error (DOM-26) | `admin_isolation::dom_26_policy_versions_append_only` |
| AC-6 | **Given** instance and account secrets **When** `Tx::begin(A)` reads **Then** it sees instance rows and A's rows only; it cannot write an instance row; `begin_admin` can; the system role can read instance rows only | `admin_isolation::d_46_secrets_policies` |
| AC-7 | **Given** the singleton **When** a second `instance_settings` row with id 2 is inserted **Then** the CHECK fails | `admin_isolation::instance_settings_singleton` |
| AC-8 | **Given** `verify_isolation` **When** run on the full M1 schema so far **Then** `Ok` | `admin_isolation::sec_06_verify_isolation_accepts_admin` |
| AC-9 | **Given** `MODULE` and the `PermissionTable` **When** built with identity's descriptor **Then** owner holds 5 admin keys, admin 11; `validate_graph([identity, admin])` passes; every `policy_keys` entry exists in the kernel registry | `descriptor::mod_08_admin_descriptor_valid` |

## Tests

```
cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
