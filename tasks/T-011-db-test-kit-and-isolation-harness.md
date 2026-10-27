---
id: T-011
title: Add the PostgreSQL test kit and the generated tenant-isolation harness
milestone: M1
kind: feature
status: in_progress
depends_on: [T-010]
requirements: [TST-02, TST-06, TST-18, SEC-AC-01, SEC-AC-02, SEC-06, CQ-18]
branch: m1-t011-db-test-kit
budget_lines: 400
assignee: Codex
---

# T-011: Add the PostgreSQL test kit and the generated tenant-isolation harness

## Goal

Any crate's tests can get a real, empty, migrated PostgreSQL database in a few lines, connected as `kinevo_app`, with injected clock and ids. One generated test proves tenant isolation for **every** tenant table and fails when a new tenant table has no fixture.

## Context

> **Test pyramid** (`spec/12-testing.md` §1): Component tests use `testcontainers` PostgreSQL 17 (and 18 in the CI matrix); one real database per test, connected **as `kinevo_app`**.
> **§4**: `kinevo-db::testing` offers `TestApp` (schema-per-test PG, injected clock, fake gateway/provider, seeded accounts). Tests own their schema; parallel safe. Fixtures are synthetic: emails `…@example.test`.
> **TST-02**: No test depends on wall-clock time, network, or unprinted random seeds.
> **TST-06**: Isolation tests run as `kinevo_app`: for every tenant table, a generated test inserts rows for two accounts and asserts account A can neither see nor write B's rows, even with a deliberately unfiltered `SELECT`/`UPDATE`/`DELETE`; and asserts RLS is enabled+forced (SEC-AC-01/02).
> **TST-18**: Each migration applies on an empty DB and on the previous schema with seed data.
> **CQ-18**: No sleeps for synchronisation; the injected clock and explicit synchronisation.
> **Environment limit** (`12-testing.md` §3): Docker may be unavailable to an unattended agent; such checks are marked `environment-limited` and never reported as passed.
> **Stack**: `testcontainers` 0.28.0 (`open-source-stack.md` row "Tests").

## Scope

**In:**
- Cargo feature `testing` of `kinevo-db` (off by default; never enabled by `kinevo-app`).
- `kinevo_db::testing::PgCluster`: starts one PostgreSQL container per test binary (lazy, `tokio::sync::OnceCell`), version from `KINEVO_TEST_PG_VERSION` (default 17), runs `bootstrap_roles` (T-009) once with fixed test passwords, and reads `KINEVO_TEST_DATABASE_URL` (a superuser URL to an existing server) instead of starting a container when set. If neither Docker nor the variable is available, `PgCluster::get()` returns `Err(Unavailable)`; the helper `require_db!()` macro prints `skipped: no database` and returns, **except** when `CI=true`, where it panics.
- `TestDb`: creates a fresh database per test (`CREATE DATABASE kinevo_t_<seq>`), grants `CONNECT` to the three roles, runs `migrate::run` with the sources passed to the builder as `kinevo_migrator`, and exposes `db() -> Db` (app and system pools as the real roles), `migrator_pool()` and `admin_pool()` (superuser, for assertions only). The database is dropped when `TestDb` is dropped (best effort, `DROP DATABASE … WITH (FORCE)`).
- `TestApp` builder (in `kinevo_db::testing`): `TestApp::builder().migrations(&[sources]).clock(FixedClock::at(...)).build().await` returning `{ db, clock: Arc<FixedClock>, ids: Arc<SeqIdGen>, tokens: Arc<SeqTokenGen>, cluster }`. Later tasks extend it by wrapping, not editing. The default fixed time is `2026-11-02T00:00:00Z` (a fixture, not a business value).
- Isolation harness `kinevo_db::testing::isolation`:
  - `IsolationFixture { table: &'static str, insert: for<'a> fn(&'a mut Tx, &'a TestIds) -> BoxFuture<'a, Result<()>> }` where `TestIds` supplies deterministic ids.
  - `register(fixtures: &'static [IsolationFixture])` (a plain slice passed to the runner; no global state).
  - `run_isolation(app: &TestApp, fixtures) -> IsolationReport` that (1) lists tenant tables from the catalog (tables with an `account_id` column, plus `accounts` and the §2.1-excluded exceptions via an explicit allow-list), (2) fails if any tenant table has no fixture and if any fixture names a non-tenant table, (3) creates accounts A and B (rows inserted through the fixture of `accounts` run first), (4) for each table inserts one row for A and one for B, (5) as A runs unfiltered `SELECT count(*)`, `UPDATE … SET <touch column>`, `DELETE` and asserts B's rows are untouched and A's affected count equals 1, (6) asserts `relrowsecurity` and `relforcerowsecurity`, (7) asserts that an `INSERT` for B in A's context is rejected. The "touch column" is provided by the fixture (`touch_sql`) to avoid guessing column names.
- A self-test using the probe table from T-010 so the harness is verified before any module table exists, plus a negative self-test: a probe table without `FORCE` makes the report contain a finding.

**Out:**
- Fixtures for module tables: each schema task registers its own (`T-020`, `T-031`, …).
- Fake gateways and providers (later milestones).
- The HTTP test server (T-016).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/Cargo.toml` | modify | feature `testing` enabling optional deps `testcontainers`, `testcontainers-modules` (postgres), `futures`, `kinevo-kernel/testing` |
| `crates/kinevo-db/src/lib.rs` | modify | `#[cfg(feature = "testing")] pub mod testing;` |
| `crates/kinevo-db/src/testing/mod.rs` | create | `PgCluster`, `TestDb`, `TestApp`, `require_db!` |
| `crates/kinevo-db/src/testing/isolation.rs` | create | harness |
| `crates/kinevo-db/tests/isolation_harness.rs` | create | self-tests (positive and negative) |
| `crates/kinevo-db/tests/migrate.rs` | modify | replace the `KINEVO_TEST_ADMIN_DATABASE_URL` approach from T-009 with `TestDb` |
| `crates/kinevo-db/tests/tx.rs` | modify | same replacement for T-010 tests |
| `spec/standards/open-source-stack.md` | modify | `testcontainers-modules` row if missing (CQ-16) |

## Interfaces

```rust
pub struct TestApp { pub db: Db, pub clock: Arc<FixedClock>, pub ids: Arc<SeqIdGen>, pub tokens: Arc<SeqTokenGen>, /* cluster handle */ }
impl TestApp { pub fn builder() -> TestAppBuilder; pub async fn new_account(&self) -> AccountId; /* allocates an id only; schema tasks add real creation */ }
pub struct TestAppBuilder; impl TestAppBuilder { pub fn migrations(self, s: &'static [MigrationSource]) -> Self; pub fn clock_at(self, t: Instant) -> Self; pub async fn build(self) -> Result<TestApp, Unavailable>; }
pub struct IsolationFixture { pub table: &'static str, pub insert: InsertFn, pub touch_sql: &'static str }
pub async fn run_isolation(app: &TestApp, fixtures: &[IsolationFixture]) -> IsolationReport; // report.findings: Vec<String>; report.assert_clean()
#[macro_export] macro_rules! require_db { ($app:ident = $builder:expr) => { … } }
```

## Business rules

1. Every tenant table needs exactly one fixture; a missing fixture is a failing finding named after the table (so adding a table without isolation coverage breaks CI).
2. Isolation assertions run with the **app** pool as `kinevo_app`; the superuser pool is used only to count rows for the cross-check "B's rows still exist".
3. Test passwords are constants in `testing` code only; the feature is never built into the release binary (a check in T-042 greps `Cargo.toml` of `kinevo-app` for the feature).

## Security

- The `testing` feature contains deliberately weak role passwords. It must be unreachable from production builds: `kinevo-app` lists `kinevo-db` without the feature in `[dependencies]`; only `[dev-dependencies]` may enable it.
- Fixtures use synthetic data (`@example.test`, marker `[fixture]`).

## Implementation notes

- Per-test databases keep tests parallel-safe and give each its own migrations. Run `CREATE DATABASE … TEMPLATE` from a pre-migrated template only if timing requires it; record any such optimisation in the Report.
- Replace timing-based waits with explicit readiness (`pg_isready` via the container's wait strategy).
- Build the harness so that `kinevo-app`'s final integration test (T-057) can call `run_isolation` with all fixtures.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** `TestApp::builder().build()` **When** two tests run in parallel **Then** each sees its own empty database and the three roles exist | `isolation_harness::test_db_is_isolated_per_test` |
| AC-2 | **Given** the app pool **When** `select current_user` runs **Then** the result is `kinevo_app`; the system pool returns `kinevo_system` | `isolation_harness::pools_use_the_real_roles` |
| AC-3 | **Given** the probe table with a fixture **When** `run_isolation` executes **Then** the report is clean | `isolation_harness::sec_ac_01_probe_table_clean` |
| AC-4 | **Given** a tenant table without a fixture **When** `run_isolation` executes **Then** a finding names the table | `isolation_harness::tst_06_missing_fixture_is_a_finding` |
| AC-5 | **Given** a probe table with RLS enabled but not forced **When** `run_isolation` executes **Then** a finding reports it | `isolation_harness::sec_ac_02_unforced_rls_is_a_finding` |
| AC-6 | **Given** `CI=true` and no database **When** `require_db!` runs **Then** it panics; without `CI` it returns `skipped` | `isolation_harness::require_db_fails_in_ci` |
| AC-7 | **Given** T-009's and T-010's tests **When** run with the test kit **Then** they pass without any `KINEVO_TEST_ADMIN_DATABASE_URL` | `migrate::*`, `tx::*` |

## Tests

```
cargo nextest run -p kinevo-db --features testing    # environment-limited if neither Docker nor KINEVO_TEST_DATABASE_URL is available
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
