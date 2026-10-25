---
id: T-020
title: Create the identity schema migration, module skeleton and descriptor
milestone: M1
kind: feature
status: todo
depends_on: [T-011, T-008, T-006]
requirements: [DOM-01, DOM-04, DOM-05, DOM-07, ARCH-20, ADR-0011, D-39, D-44, D-45, CA-02, CA-04, CA-18, SEC-AC-01, SEC-AC-02]
branch: m1-t020-identity-schema
budget_lines: 380
assignee: ""
---

# T-020: Create the identity schema migration, module skeleton and descriptor

## Goal

The `identity` crate has its layered skeleton, its module descriptor (permissions, policies, jobs, subscriptions) and the migration that creates its seven tables with the exact RLS, role-scoped policies and grants from the spec. The isolation harness covers every identity table.

## Context

> **Module layout** (`02-architecture.md` §3, CA-02): `lib.rs` exposes the `Module` descriptor, `router()`, `migrations()`, `jobs()`, `subscribers()` (plus `provisioners()` and `exporters()`, D-43); `domain/` pure; `app/` use cases; `ports.rs`; `infra/` repositories on the module's **own tables only**; `http/`; `migrations/`.
> **Tables** (`06-domain-model.md` §4): `accounts`, `refresh_token_families`, `refresh_tokens`, `recovery_codes`, `password_resets`, `invites`, `account_exports`. Copy the DDL exactly, including `accounts.last_login_at` (D-45), `refresh_token_families.mfa`, `refresh_tokens.token_hash UNIQUE`, `refresh_tokens_family` index, the `account_status_ck`, `account_locale_ck`, `rtf_reason_ck`, `export_state_ck` constraint names, and `citext` email.
> **RLS** (`06` §2, ADR-0011, D-45): every tenant table has RLS enabled **and forced** with `tenant_isolation` on `account_id`; **`accounts` uses `id`** instead. The tables `accounts`, `refresh_token_families`, `refresh_tokens`, `password_resets` additionally get `CREATE POLICY system_auth ON <table> TO kinevo_system USING (true) WITH CHECK (true)`. `invites` is instance-level: no `account_id`, no RLS.
> **Grants**: `kinevo_app`: DML (SELECT, INSERT, UPDATE, DELETE) on every identity tenant table; on `invites` SELECT, INSERT, UPDATE (creation requires an admin `Tx` in code, consumption happens in the registration transaction, D-39). `kinevo_system`: `accounts`, `refresh_token_families`, `refresh_tokens`, `password_resets` SELECT, INSERT, UPDATE; `invites` SELECT. No other grants to the system role (`06` §2.2).
> **Migration file** (D-44): `crates/kinevo-mod-identity/migrations/20261101000100_identity_accounts.sql`; it runs before the platform tables; FKs only to tables created earlier (only `accounts` self-references and children).
> **DOM-05**: mutable aggregates have `version bigint not null default 1`. **DOM-07**: emails stored lower-cased and NFC-normalised (`citext`). **DOM-04**: deleting an account cascades to its data.
> **Permissions** (`identity.md` §1 and `rbac.md` §2): owner and admin: `identity.session.manage`, `identity.account.read`, `identity.account.update`, `identity.account.delete`, `identity.account.export`, `identity.totp.manage`; admin only: `identity.admin.accounts.read`, `identity.admin.accounts.update`, `identity.admin.invite.create`, `identity.admin.password_reset.issue`.
> **Policy keys owned** (`04-policies.md` §3.3): `auth.registration.mode`, `auth.password.min_length`, `auth.access_token.ttl`, `auth.refresh_token.absolute_ttl`, `auth.refresh_token.idle_ttl`, `auth.lockout.max_attempts`, `auth.lockout.window`, `auth.lockout.duration`, `auth.totp.required`, `auth.refresh.grace`, `identity.purge.delay`, `auth.invite.ttl`, `auth.password_reset.ttl`, `export.max_age_days`.
> **Jobs and events** (`identity.md` §7): jobs `account.purge`, `account.export.build`, `identity.sessions.prune`, `identity.resets.prune`, `identity.exports.prune`; emits `AccountRegistered`, `AccountLoggedIn`, `AccountDisabled`, `AccountDeletionRequested`, `PasswordChanged`; consumes nothing.
> **Messages**: namespace `identity` (keys are added by the tasks that need them; the namespace must exist in `en` and `id`, MOD-08).

## Scope

**In:**
- Crate skeleton: `src/{lib.rs, domain/mod.rs, app/mod.rs, ports.rs, infra/mod.rs, http/mod.rs}` and `migrations/`. `lib.rs` exports `pub const MODULE: ModuleDescriptor` (key `identity`, kind core, no `requires`), `pub fn migrations() -> MigrationSource`, empty-for-now stubs `router()`, `jobs()`, `subscribers()`, `provisioners()`, `exporters()` returning empty collections (later tasks fill them).
- The migration with: tables; `CREATE INDEX`es from the spec; constraints; RLS (`ENABLE` + `FORCE`); `tenant_isolation` policies; `system_auth` policies; grants.
- Constraint registry for `kinevo_db::errors` (T-010): `pub const CONSTRAINTS: ConstraintMap` mapping `accounts_email_key` → `email_taken`; others as later tasks need them (add entries in the owning task).
- Domain value types shared by the module (no behaviour yet): `AccountStatus` (`Active|Disabled|Deleting`, `as_str`/`parse`), `RevokeReason` (`Logout|ReuseDetected|PasswordChanged|Admin|Expired`), `ExportState` (`Queued|Running|Ready|Failed|Expired`), id aliases `AccountId` (kernel), `FamilyId` (T-017), `RefreshTokenId`, `InviteId`, `ResetId`, `ExportId`.
- Isolation fixtures for the six tenant tables in `crates/kinevo-mod-identity/tests/support/fixtures.rs`, and a test `identity_isolation` running `run_isolation` (T-011) with the platform migration sources plus identity.

**Out:**
- Repositories and any behaviour: T-021 onwards.
- Platform tables (`audit_log` etc.): T-012 (runs after this migration).
- Route handlers.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/Cargo.toml` | modify | deps `kinevo-kernel`, `kinevo-ports`, `kinevo-db`, `kinevo-http`, `sqlx` (migrate), `serde`; dev: `kinevo-db` with `testing` |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | descriptor and stubs |
| `crates/kinevo-mod-identity/src/domain/mod.rs` | create | status/state enums |
| `crates/kinevo-mod-identity/src/{app,infra,http}/mod.rs`, `src/ports.rs` | create | empty with module docs |
| `crates/kinevo-mod-identity/migrations/20261101000100_identity_accounts.sql` | create | |
| `crates/kinevo-mod-identity/src/infra/constraints.rs` | create | `CONSTRAINTS` |
| `crates/kinevo-mod-identity/tests/identity_isolation.rs` | create | |
| `crates/kinevo-mod-identity/tests/support/fixtures.rs` | create | |
| `crates/kinevo-mod-identity/tests/descriptor.rs` | create | |

## Interfaces

```rust
pub const MODULE: ModuleDescriptor = ModuleDescriptor { key: "identity", kind: ModuleKind::Core, requires: &[],
    permissions: &[PermissionDef { key: "identity.session.manage", owner: true }, /* … 6 owner keys … */ PermissionDef { key: "identity.admin.accounts.read", owner: false } /* … 4 admin keys … */],
    policy_keys: &[/* the 14 keys above */], jobs: &["account.purge","account.export.build","identity.sessions.prune","identity.resets.prune","identity.exports.prune"],
    subscriptions: &[], message_namespaces: &["identity"], /* nav, settings_panels added by the web tasks via descriptor edits */ .. };
pub fn migrations() -> MigrationSource;
```

## Business rules

1. `accounts_email_key` is unique on the citext email; the unique violation maps to `email_taken` (409).
2. `refresh_tokens.token_hash` is unique; a family's tokens cascade-delete with the family.
3. `accounts.status` ∈ `active|disabled|deleting`; locale ∈ `en|id`; `week_start` 1–7; `display_name` 1–80 chars.
4. The `invites` table has no tenant column and no RLS; `password_resets`, `recovery_codes`, `account_exports` are tenant tables.
5. The system role cannot read `recovery_codes` or `account_exports` (no grant).

## Security

- Token and code columns are hashes (`bytea`), never plaintext (DOM-10). No column stores a TOTP seed (`SecretStore`, D-40).
- `system_auth` is limited `TO kinevo_system`; `verify_isolation()` (T-010) accepts exactly the allowed policy set.
- Run the migration as `kinevo_migrator`; grants are explicit in the file (no default privileges).

## Implementation notes

- Policy expression text is the standard one from `06` §2; for `accounts` the expression compares `id`.
- Constraint names in the DDL must match the spec names, because the mapping and `verify_isolation` use them.
- Keep SQL in `migrations/` and `infra/`; no SQL in `domain`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** column/constraint/index metadata of the seven tables is compared to the spec DDL **Then** they match | `identity_isolation::dom_schema_matches_spec_ddl` |
| AC-2 | **Given** the fixtures **When** `run_isolation` executes as `kinevo_app` **Then** the report is clean for all six tenant tables (and `invites` is in the harness' no-RLS allow-list) | `identity_isolation::sec_ac_01_identity_tables_isolated` |
| AC-3 | **Given** the system role **When** it selects `accounts`, `refresh_tokens`, `password_resets` without any context **Then** it sees all rows; on `recovery_codes` and `account_exports` it gets a privilege error | `identity_isolation::adr_0011_system_auth_scope` |
| AC-4 | **Given** `kinevo_app` without a context **When** it selects `accounts` **Then** zero rows; with `Tx::begin(A)` only A's row | `identity_isolation::sec_05_accounts_policy_uses_id` |
| AC-5 | **Given** `Tx::begin(A)` **When** it inserts an `accounts` row with id B **Then** the RLS check rejects it; with id A it succeeds | `identity_isolation::adr_0011_account_insert_only_for_own_id` |
| AC-6 | **Given** two `accounts` with emails `A@Example.test` and `a@example.TEST` **When** inserted **Then** the second fails with the `accounts_email_key` violation, which maps to `email_taken` | `identity_isolation::dom_07_email_unique_case_insensitive` |
| AC-7 | **Given** `verify_isolation` **When** run against the migrated schema (platform extensions + identity) **Then** `Ok` with exactly the four `system_auth` policies | `identity_isolation::sec_06_verify_isolation_accepts_identity` |
| AC-8 | **Given** `MODULE` and the `PermissionTable` **When** built **Then** owner holds 6 keys, admin 10; `validate_graph` passes with `admin` absent (identity has no `requires`) | `descriptor::mod_08_descriptor_keys_exist` |
| AC-9 | **Given** every `policy_keys` entry **When** looked up in the kernel registry (T-006) **Then** all 14 exist | `descriptor::mod_08_policy_keys_registered` |

## Tests

```
cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
