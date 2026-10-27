---
id: T-010
title: Add pools, the three transaction contexts, verify_isolation and constraint mapping to kinevo-db
milestone: M1
kind: feature
status: in_progress
depends_on: [T-009, T-002, T-003, T-004]
requirements: [ARCH-22, ARCH-24, CA-08, CA-09, SEC-06, ADR-0011, D-39, D-45, D-46, VAL-11, ERR-05, CQ-11]
branch: m1-t010-db-tx-contexts
budget_lines: 420
assignee: Codex
---

# T-010: Add pools, the three transaction contexts, verify_isolation and constraint mapping to kinevo-db

## Goal

All database access goes through `Tx::begin`, `Tx::begin_admin` or `SystemTx::begin`; nothing else can set the RLS context. `verify_isolation()` refuses to start an unsafe database, and database constraint failures become registry error codes.

## Context

> **ARCH-22**: `kinevo-db::Tx::begin(account_id)` executes `SET LOCAL app.account_id = $1` (via `set_config(..., true)`) as the first statement. Repositories take `&mut Tx` and cannot be constructed without one. Code outside `kinevo-db` cannot obtain a raw connection to tenant tables (CA-09).
> **ARCH-24 / ADR-0011 / D-39**: Three contexts, all created only by `kinevo-db`: `Tx::begin(account)` (`kinevo_app`, every authenticated request and per-account job), `Tx::begin_admin(account)` (`kinevo_app` + `app.instance_admin = 'on'`, for instance-level writes by an `instance_admin` and setup Finish) and `SystemTx` (`kinevo_system`, only login lookup/counters, refresh rotation, worker claim/dispatch, instance maintenance). The system role reaches tenant data only through the role-scoped `system_auth` policies on the auth tables.
> **ADR-0011**: `kinevo_app` must never accept `app.instance_admin` from a client. Only `kinevo-db` sets it, after the HTTP layer has loaded the account and confirmed `is_instance_admin`. A test asserts that no other crate calls `set_config`.
> **CA-08**: A use case that changes more than one row or emits events opens exactly one `Tx` and passes `&mut Tx` down; no nested transactions; no network call inside a transaction.
> **SEC-06**: `kinevo_app` is not superuser, has no `BYPASSRLS`, owns no tenant table; every tenant table has RLS enabled **and forced** with the standard policy; `kinevo-db::verify_isolation()` aborts startup otherwise. The only extra policies allowed are `system_auth` on `accounts`, `refresh_token_families`, `refresh_tokens`, `password_resets` (scoped `TO kinevo_system`), the admin-context rules on `policy_versions` and `audit_log`, `instance_secrets_read`/`instance_secrets_write` on `secrets` (D-46), `system_insert` on `audit_log` and `retention` on `audit_log` and `idempotency_keys` (D-45, T-012); `verify_isolation()` compares the exact policy set and fails on any other.
> **Standard policy text** (`06-domain-model.md` §2): `tenant_isolation` with `USING`/`WITH CHECK (account_id = NULLIF(current_setting('app.account_id', true), '')::uuid)`. `accounts` uses `id` instead of `account_id`.
> **VAL-11 / ERR-05**: Constraint violations (unique, FK, CHECK) are translated to registry codes by constraint name; an unmapped violation is a bug: `internal_error` 500 and a log event `unmapped_constraint` with the constraint name.
> **CQ-11**: Only `sqlx`; parameters only; no `SELECT *`; queries in repositories.
> **Pools** (`11-operations.md` §2): `KINEVO_DATABASE_URL` (app role), `KINEVO_SYSTEM_DATABASE_URL` (system role), `KINEVO_MIGRATOR_DATABASE_URL` (migrations only); `KINEVO_DB_POOL_MAX` default 10; `db.connect.retries` default 10 is a bootstrap value from config.

## Scope

**In:**
- `kinevo_db::Db { app: PgPool, system: PgPool }`, `Db::connect(app_url, system_url, pool_max, retries, backoff)` (retry loop with injected sleep so tests do not wait; returns `ConnectError` after the last attempt). Private fields.
- `Tx` (a newtype over `sqlx::Transaction<'static, Postgres>` plus the `AccountId`): `Tx::begin(&Db, AccountId)`, `Tx::begin_admin(&Db, AccountId)` (precondition documented: caller verified `is_instance_admin`), `commit(self)`, `rollback(self)`, `account_id()`, `conn(&mut self) -> &mut PgConnection` for repositories. The first statement sets `app.account_id` (and for admin `app.instance_admin='on'`) with `set_config($1,$2,true)` via bound parameters.
- `SystemTx::begin(&Db)`, `commit`, `rollback`, `conn()`. `SystemTx` cannot be converted to `Tx`.
- `verify_isolation(pool) -> Result<(), IsolationError>` implementing SEC-06: current role not superuser/bypassrls/createdb/createrole; the role owns no table in schema `public`; every table with a column `account_id` (and `accounts`) has `relrowsecurity` and `relforcerowsecurity` true and a policy named `tenant_isolation` with the exact expression; the policy set per table equals the allow-list; `kinevo_system` has no grant on tables outside the §2.2 list. Returns a list of findings (no early exit), `IsolationError::Findings(Vec<Finding>)`. Findings name tables and policies only.
- `kinevo_db::errors`: `ConstraintMap(&'static [(&'static str, ErrorCode)])`, `ConstraintRegistry` (built by the app from each module's map), `map_error(&ConstraintRegistry, sqlx::Error) -> AppError` with rules: unique (23505), FK (23503) and check (23514) violations are looked up by constraint name; unknown → `internal_error` plus a `tracing::error!` with `event="unmapped_constraint"` and `constraint_name`; pool timeout / IO / `57P03` → `service_unavailable`; serialization failure `40001` and deadlock `40P01` → `service_unavailable` with retry class `backoff`; `RowNotFound` → `not_found`.
- A source-scan test: `set_config(` and `SET LOCAL` appear in no file outside `crates/kinevo-db/src`.

**Out:**
- Pool metrics, `/readyz` wiring: T-016/T-039.
- Migrations and tables: T-020 onwards. `verify_isolation` must pass trivially with no tenant tables and be exercised against real tables by T-020's task.
- The isolation test harness: T-011.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/Cargo.toml` | modify | add `tracing`, `kinevo-ports` is **not** a dependency |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod pool; pub mod tx; pub mod isolation; pub mod errors;` re-exports |
| `crates/kinevo-db/src/pool.rs` | create | `Db`, `connect` |
| `crates/kinevo-db/src/tx.rs` | create | `Tx`, `SystemTx` |
| `crates/kinevo-db/src/isolation.rs` | create | `verify_isolation`, allow-lists |
| `crates/kinevo-db/src/errors.rs` | create | constraint mapping |
| `crates/kinevo-db/tests/tx.rs` | create | component tests |
| `crates/kinevo-db/tests/no_set_config_outside_db.rs` | create | source scan across `crates/*/src` |

## Interfaces

```rust
pub struct Db { /* app: PgPool, system: PgPool */ }
impl Db { pub async fn connect(cfg: &DbConfig, sleeper: &dyn Sleeper) -> Result<Self, ConnectError>; pub fn app_pool_for_health(&self) -> &PgPool; }
pub struct Tx { /* account: AccountId, inner: Transaction */ }
impl Tx { pub async fn begin(db: &Db, a: AccountId) -> Result<Self, AppError>; pub async fn begin_admin(db: &Db, a: AccountId) -> Result<Self, AppError>;
          pub fn account_id(&self) -> AccountId; pub fn conn(&mut self) -> &mut PgConnection;
          pub async fn commit(self) -> Result<(), AppError>; pub async fn rollback(self) -> Result<(), AppError>; }
pub struct SystemTx { /* inner */ }
impl SystemTx { pub async fn begin(db: &Db) -> Result<Self, AppError>; pub fn conn(&mut self) -> &mut PgConnection; pub async fn commit(self) -> Result<(), AppError>; }
pub async fn verify_isolation(pool: &PgPool) -> Result<(), IsolationError>;
pub fn map_error(reg: &ConstraintRegistry, e: sqlx::Error) -> AppError;
```

`DbConfig { app_url: Secret<String>, system_url: Secret<String>, pool_max: u32, retries: u32 }`. `app_pool_for_health` exposes the pool only for a `SELECT 1` readiness probe; the method name and doc comment say so, and an architecture test (T-042) forbids other uses.

## Business rules

1. A `Tx` always sets the account context before any other statement. A repository can never run on a connection without it.
2. `Tx::begin_admin` sets both settings; there is no way to set `app.instance_admin` without also setting `app.account_id`.
3. Dropping a `Tx` or `SystemTx` without commit rolls back (sqlx behaviour; test it).
4. `verify_isolation` lists **all** findings, ordered by table name, before failing.
5. Retry on connect uses `retries` attempts with exponential backoff from the injected `Sleeper`; there are no wall-clock sleeps in tests.

## Security

- `app.account_id` and `app.instance_admin` are set only here. The source-scan test fails the build if another file contains `set_config(` or `SET LOCAL`.
- Connection URLs are `Secret<String>`; `ConnectError`'s `Display` never contains them.
- `map_error` never puts the database message, SQL or values into `details`: only the mapped registry code (ERR-03).
- The 404-for-foreign-rows rule (SEC-07) relies on RLS, not on code here; `RowNotFound` → `not_found`.

## Implementation notes

- Component tests here use a throwaway table created in the test by the migrator (not a module table): `CREATE TABLE t_probe (id uuid primary key, account_id uuid not null)` + `FORCE` RLS and the standard policy, then grant DML to `kinevo_app`. Drop it afterwards. T-011 provides the container; until it merges use the same env-var approach as T-009 and mark `environment-limited` if no database is available.
- Do not use `SET LOCAL` text interpolation; use `SELECT set_config('app.account_id', $1, true)`.
- `verify_isolation` reads `pg_class`, `pg_policy`, `pg_roles`, `information_schema.role_table_grants`. These are catalog reads, allowed as `kinevo_app`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** rows for accounts A and B in `t_probe` **When** a `Tx::begin(A)` runs an unfiltered `SELECT` **Then** only A's rows are returned; with `begin(B)` only B's | `tx::arch_22_tx_scopes_rows_to_account` |
| AC-2 | **Given** a `Tx` for A **When** an `INSERT` with B's `account_id` runs **Then** it fails the RLS check | `tx::arch_22_with_check_blocks_foreign_insert` |
| AC-3 | **Given** a plain pool connection (no `Tx`) as `kinevo_app` **When** `SELECT` from `t_probe` **Then** zero rows (deny by default) | `tx::sec_05_no_context_sees_nothing` |
| AC-4 | **Given** `Tx::begin` **When** `current_setting('app.instance_admin', true)` is read **Then** it is empty; with `begin_admin` it is `on` | `tx::adr_0011_admin_flag_only_in_admin_tx` |
| AC-5 | **Given** a `Tx` dropped without commit **When** the row it inserted is read afterwards **Then** it is absent | `tx::drop_rolls_back` |
| AC-6 | **Given** a probe table without `FORCE`, and another without a policy **When** `verify_isolation` runs **Then** both are in the findings, together | `tx::sec_06_verify_isolation_lists_all_findings` |
| AC-7 | **Given** a correctly configured probe table **When** `verify_isolation` runs **Then** `Ok` | `tx::sec_06_verify_isolation_ok` |
| AC-8 | **Given** a unique violation on a mapped constraint and on an unmapped one **When** `map_error` runs **Then** the first yields the mapped code (409/422 per registry), the second `internal_error` and a captured `unmapped_constraint` log with the constraint name only | `errors::tests::val_11_constraint_mapping` |
| AC-9 | **Given** all `crates/*/src` files **When** scanned **Then** `set_config(`/`SET LOCAL` occur only under `crates/kinevo-db/src` | `no_set_config_outside_db::adr_0011_only_kinevo_db_sets_context` |
| AC-10 | **Given** a failing connect with `retries = 3` and a fake sleeper **When** `Db::connect` runs **Then** it tries 4 times with doubling delays and returns `ConnectError` without the URL in its message | `pool::tests::connect_retries_with_backoff_and_hides_url` |

## Tests

```
cargo nextest run -p kinevo-db
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
