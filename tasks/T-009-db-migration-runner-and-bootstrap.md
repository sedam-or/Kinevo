---
id: T-009
title: Add the multi-crate migration runner and the role bootstrap SQL to kinevo-db
milestone: M1
kind: feature
status: review
depends_on: [T-001, T-003]
requirements: [CA-18, D-40, D-44, ARCH-21, DOM-04, TST-18, SEC-06]
branch: m1-t009-db-migration-runner
budget_lines: 380
assignee: Codex
---

# T-009: Add the multi-crate migration runner and the role bootstrap SQL to kinevo-db

## Goal

`kinevo-db` can apply the migrations of every crate in one global timestamp order as `kinevo_migrator`, refuses a changed or out-of-order migration, and ships the documented bootstrap SQL that creates the three database roles. The first platform migration (extensions) exists.

## Context

> **CA-18** (`spec/standards/clean-architecture.md`): Migrations are append-only and forward-only in production: a released migration file is never edited; fixes are new migrations. Each migration runs in a transaction unless it uses `CREATE INDEX CONCURRENTLY` (then it is in its own file marked non-transactional). Each module owns its migration directory; global ordering by timestamp prefix `YYYYMMDDHHMMSS_<module>_<name>.sql`. Check: CI migration checksum test.
> **D-40(a)**: Files live at `crates/<crate>/migrations/YYYYMMDDHHMMSS_<module>_<name>.sql`; `kinevo-db` uses module name `platform`. A migration creates or alters only its own module's objects, and foreign keys may point only to tables created by an earlier migration.
> **D-44** (`spec/decisions.md`): The runner uses sqlx's `_sqlx_migrations`. Order: `platform` extensions (first), `identity` schema, `platform` tables, `admin`, then later modules. Fixed timestamps: `20261101000000_platform_extensions`, `20261101000100_identity_accounts`, `20261101000200_platform_tables`, `20261101000300_admin_instance`; M2+ modules use `20261201000000` and above.
> **ARCH-21** (`02-architecture.md` §6): The application connects with role `kinevo_app` (no `SUPERUSER`, no `BYPASSRLS`, not the table owner). Migrations run with role `kinevo_migrator` (owner). Roles are created by `kinevo migrate` or by the documented bootstrap SQL.
> **Roles** (`06-domain-model.md` §2): `kinevo_migrator`: owns all objects, NOSUPERUSER, NOBYPASSRLS. `kinevo_app`: NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE; DML on tables; no DDL. `kinevo_system`: NOSUPERUSER NOBYPASSRLS; DML only on the tables listed in §2.2. Role passwords come from env, never from SQL files. First platform migration: `CREATE EXTENSION IF NOT EXISTS citext; CREATE EXTENSION IF NOT EXISTS btree_gist;`.
> **TST-18**: Each migration applies on an empty DB and on the previous schema with seed data; destructive migrations require a backup note in the PR.
> **ARCH-40**: A failed migration rolls back its transaction (per file); the process exits; never partial.

## Scope

**In:**
- `kinevo_db::migrate`:
  - `MigrationSource { crate_name: &'static str, migrator: &'static sqlx::migrate::Migrator }` (each crate exposes one through `sqlx::migrate!("./migrations")`).
  - `merge(sources) -> Result<Migrator, MigrateError>`: orders all migrations by version, rejects duplicate versions across crates, rejects a file whose module segment does not match the crate's module name (the crate declares it), and rejects a version lower than the highest version already applied (out-of-order).
  - `run(url_migrator, sources) -> Result<Applied, MigrateError>`; `status(pool, sources)` returning applied/pending counts used by `/readyz` (T-016/T-039) and `Applied { count, last_version }`.
  - Checksum verification is sqlx's; add a test that a modified applied file fails (`MigrateError::ChecksumMismatch`).
- Bootstrap: `crates/kinevo-db/bootstrap/roles.sql` (a psql-style script using `:'migrator_password'`, `:'app_password'`, `:'system_password'` variables; idempotent via `DO` blocks; sets `NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS`; creates no objects) and `bootstrap::bootstrap_roles(admin_url, creds, database_name)` (D-50, D-56), which is **SQLx-native** and does not run `roles.sql` (the psql script is for operators who bootstrap by hand, `11-operations.md` §1 channel B). Credentials come only from the three role URLs: `RoleCredentials::from_urls(migrator_url, app_url, system_url)` takes each password (percent-decoded) from the URL's userinfo, requires the user names to be exactly `kinevo_migrator`, `kinevo_app`, `kinevo_system`, and returns the database name from the app URL path. There are no separate password variables. Each role is created or altered by a server-side statement built with `format('%I'/'%L')` from bind parameters (`SELECT format('CREATE ROLE %I LOGIN … PASSWORD %L', $1, $2)`, then the returned text is executed; if the role exists, `ALTER ROLE` is used), so Rust never concatenates a password into SQL. It also performs the grants (`GRANT CONNECT`, `GRANT CREATE ON SCHEMA public TO kinevo_migrator`, default privileges so tables created by `kinevo_migrator` grant nothing to the other roles automatically; each migration grants explicitly).
- First migration: `crates/kinevo-db/migrations/20261101000000_platform_extensions.sql`.
- `kinevo_db::migrations_source()` returning this crate's `MigrationSource` (`crate_name = "kinevo-db"`, module `platform`).

**Out:**
- The `migrate` CLI subcommand: T-040.
- The test database fixtures (`TestApp`): T-011.
- Tables of any module.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/Cargo.toml` | modify | deps `sqlx` (postgres, runtime-tokio, tls-rustls, migrate, uuid), `tokio`, `thiserror`, `kinevo-kernel` |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod migrate; pub mod bootstrap;` |
| `crates/kinevo-db/src/migrate.rs` | create | merge/run/status |
| `crates/kinevo-db/src/bootstrap.rs` | create | executes `roles.sql` |
| `crates/kinevo-db/bootstrap/roles.sql` | create | |
| `crates/kinevo-db/migrations/20261101000000_platform_extensions.sql` | create | |
| `crates/kinevo-db/tests/migrate.rs` | create | needs PostgreSQL; see Implementation notes |
| `spec/standards/open-source-stack.md` | modify | confirm `sqlx` and `tokio` rows list the features used |

## Interfaces

```rust
pub struct MigrationSource { pub crate_name: &'static str, pub module: &'static str, pub migrator: &'static sqlx::migrate::Migrator }
pub fn merge(sources: &[MigrationSource]) -> Result<sqlx::migrate::Migrator, MigrateError>;
pub async fn run(migrator_url: &Secret<String>, sources: &[MigrationSource]) -> Result<Applied, MigrateError>;
pub async fn status(pool: &PgPool, sources: &[MigrationSource]) -> Result<MigrationStatus, MigrateError>; // { applied, pending, up_to_date }
pub enum MigrateError { Duplicate{version}, WrongModule{file,expected}, OutOfOrder{version}, ChecksumMismatch{version}, Db(sqlx::Error) }
pub struct RoleCredentials { /* three Secret<String> passwords, private */ }
impl RoleCredentials { pub fn from_urls(migrator: &Secret<String>, app: &Secret<String>, system: &Secret<String>) -> Result<(Self, String /* database name */), BootstrapError>; }
pub async fn bootstrap_roles(admin_url: &Secret<String>, creds: &RoleCredentials, database_name: &str) -> Result<(), BootstrapError>;
```

The `MigrateError` display never prints a connection URL.

## Business rules

1. Order is by numeric version prefix across all sources; ties are errors.
2. A file name must match `^\d{14}_<module>_[a-z0-9_]+\.sql$` where `<module>` equals the source's module (D-40a).
3. A migration that contains `CREATE INDEX CONCURRENTLY` must be in a file whose name ends `_nontx.sql` and the runner executes it outside a transaction (sqlx supports `-- no-transaction` as the first line; require that marker, test it).
4. Running twice is a no-op; the second `run` reports `count = 0`.

## Security

- Passwords reach SQL only as bind parameters of a `SELECT format(… %I … %L …)` evaluated by PostgreSQL; never string-concatenated by Rust. `roles.sql` (operator script) takes psql variables and is not executed by Rust. Passwords are `Secret<String>` and never logged.
- The script creates roles `NOSUPERUSER NOBYPASSRLS` explicitly (SEC-06).
- `roles.sql` contains no passwords and no environment-specific database name.

## Implementation notes

- Component tests need a live PostgreSQL. Until T-011 exists, the integration test reads `KINEVO_TEST_ADMIN_DATABASE_URL`; if unset it prints `skipped: no database` and **fails** when `CI=true` (so CI cannot silently skip). Mark this check `environment-limited` in the Report if Docker/PostgreSQL is unavailable and state what was not run. T-011 replaces the env var with testcontainers.
- Unit-test `merge` with in-memory `Migrator`s built from `Migration::new(...)`, no database needed.
- Keep `roles.sql` free of `CREATE DATABASE`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** two sources with interleaved versions **When** merged **Then** the output is sorted globally | `migrate::tests::ca_18_global_order_by_timestamp` |
| AC-2 | **Given** two sources with the same version **When** merged **Then** `Duplicate` | `migrate::tests::duplicate_versions_rejected` |
| AC-3 | **Given** a file `…_identity_x.sql` in the `admin` source **When** merged **Then** `WrongModule` | `migrate::tests::d_40_file_module_matches_source` |
| AC-4 | **Given** an applied database **When** a source adds a version lower than the highest applied **Then** `OutOfOrder` | `migrate::tests::out_of_order_rejected` |
| AC-5 | **Given** an empty database and bootstrapped roles **When** `run` executes **Then** `citext` and `btree_gist` exist, `count = 1`, and a second run applies 0 | `tests/migrate::platform_extensions_apply_once` |
| AC-6 | **Given** an applied migration whose file text is changed **When** `run` executes **Then** `ChecksumMismatch` | `tests/migrate::ca_18_checksum_change_detected` |
| AC-7 | **Given** bootstrapped roles **When** `pg_roles` is queried **Then** none of the three has `rolsuper`, `rolbypassrls`, `rolcreatedb`, `rolcreaterole`, and bootstrap run twice succeeds | `tests/migrate::arch_21_roles_are_unprivileged_and_idempotent` |
| AC-8 | **Given** role URLs whose user names are correct, or one wrong **When** `RoleCredentials::from_urls` runs **Then** passwords and the database name are extracted, and a wrong user name or a missing password is an error that never prints the URL | `bootstrap::tests::d_50_credentials_from_urls` |

## Tests

```
cargo nextest run -p kinevo-db
KINEVO_TEST_ADMIN_DATABASE_URL=… cargo nextest run -p kinevo-db   # component tests; environment-limited without PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Only the files listed under *Files* changed; generated lockfile and task index are recorded below; line budget overage is guidance under D-55.
- [x] Report below is filled in.

## Report

### Summary

Implemented the globally ordered SQLx migration runner, migration status/checksum checks, SQLx-native role bootstrap, operator psql bootstrap script, and the first platform extension migration. All eight acceptance criteria and the repository gate pass.

### Acceptance results

| Criterion | Named test | Result |
|---|---|---|
| AC-1 | `migrate::tests::ca_18_global_order_by_timestamp` | pass |
| AC-2 | `migrate::tests::duplicate_versions_rejected` | pass |
| AC-3 | `migrate::tests::d_40_file_module_matches_source` | pass |
| AC-4 | `migrate::tests::out_of_order_rejected` | pass |
| AC-5 | `tests/migrate::platform_extensions_apply_once` | pass |
| AC-6 | `tests/migrate::ca_18_checksum_change_detected` | pass |
| AC-7 | `tests/migrate::arch_21_roles_are_unprivileged_and_idempotent` | pass |
| AC-8 | `bootstrap::tests::d_50_credentials_from_urls` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; scripts/preflight.sh` | 0 | No hard blockers; Docker and PostgreSQL test image available. |
| `bash -c 'eval "$(scripts/dev-postgres.sh env)" && export KINEVO_TEST_ADMIN_DATABASE_URL="$KINEVO_TEST_DATABASE_URL" && cargo nextest run -p kinevo-db'` | 0 | 9 passed, 0 skipped. |
| `bash -c 'eval "$(scripts/dev-postgres.sh env)" && export KINEVO_TEST_ADMIN_DATABASE_URL="$KINEVO_TEST_DATABASE_URL" && just check'` | 0 | 66 tests passed; fmt, clippy, cargo-deny, spec check, contrast, and task-index checks passed. Existing clippy.toml unreachable-method and cargo-deny duplicate/license-not-encountered warnings remain non-failing. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md`; 0 problems in 132 tasks. |
| `python3 scripts/tasks_index.py --check` | 0 | 0 problems in 132 tasks. |

### Files changed

`crates/kinevo-db/Cargo.toml`, `crates/kinevo-db/src/lib.rs`, `crates/kinevo-db/src/migrate.rs`, `crates/kinevo-db/src/bootstrap.rs`, `crates/kinevo-db/bootstrap/roles.sql`, `crates/kinevo-db/migrations/20261101000000_platform_extensions.sql`, `crates/kinevo-db/tests/migrate.rs`, `spec/standards/open-source-stack.md`, generated `Cargo.lock`, this task file, and generated `tasks/index.md`.

### Deviations

Q-3: database-level `CREATE` is granted to `kinevo_migrator` during bootstrap because SQLSTATE `42501` showed that schema-level `CREATE` alone did not permit this non-superuser to install trusted extensions. This adds database `CREATE` beyond the task's original grant list; pending architect review.

SQLx uses `tls-rustls-ring-native-roots` instead of the task's `tls-rustls` feature alias. The default rustls root feature pulled `webpki-roots` with a CDLA-Permissive-2.0 license that failed cargo-deny. Native roots keep rustls and make the license gate pass; the stack entry reflects the selected feature. The generated `Cargo.lock` records the resulting dependency graph.

### Spec issues noticed

Q-1 and Q-2 were resolved by D-56. Q-3 is listed under Questions as an implementer resolution pending architect review.

### Environment-limited

None.

### Not done / not verified

All acceptance criteria were run. PostgreSQL-backed tests used the repository's throwaway PostgreSQL environment. The operator bootstrap script was also executed against the development database with synthetic test passwords; its three role DO blocks completed successfully.

## Questions

- Q-1 (implementer, 2026-10-26): T-009's `bootstrap::run(admin_url, passwords: Secret<…> ×3, database_name)` and `bootstrap_roles(admin_url, pw: BootstrapPasswords)` require separate role passwords. `spec/decisions.md` D-50 says the bootstrap command uses `KINEVO_BOOTSTRAP_DATABASE_URL` and role passwords embedded in the migrator/app/system database URLs, and explicitly says no separate password variables exist.
  Options: (a) revise the task interfaces to accept the bootstrap and role database URLs, following D-50; (b) supersede D-50 with a new decision allowing separate password values.
  Recommendation: (a), because D-50 is already an accepted decision.
  Resolved (architect): D-56
- Q-2 (implementer, 2026-10-26): `roles.sql` must use psql `:'variable'` substitutions, but the task says an async SQLx function executes it. SQLx sends SQL to PostgreSQL and does not perform psql variable substitution; no psql runtime dependency or safe invocation contract is specified.
  Options: (a) define a SQLx-native, parameter-safe bootstrap executor and clarify that the psql script is for operators; (b) specify a psql runtime invocation and a secret-safe way to pass variables from Rust.
  Recommendation: (a), because it keeps role creation on the existing SQLx path without adding an external runtime requirement.
  Resolved (architect): D-56
- Q-3 (implementer, 2026-10-27): AC-5 failed with SQLSTATE `42501` when `kinevo_migrator` installed the first trusted extensions. `GRANT CREATE ON SCHEMA public` alone did not permit `CREATE EXTENSION` on the target database.
  Options: (a) grant database-level `CREATE` to `kinevo_migrator` during bootstrap; (b) require an operator to install the extensions before running migrations.
  Recommendation: (a), so the first migration remains part of the SQLx runner and the role remains non-superuser.
  Resolved by implementer, pending architect review.
