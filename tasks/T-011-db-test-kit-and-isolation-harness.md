---
id: T-011
title: Add the PostgreSQL test kit and the generated tenant-isolation harness
milestone: M1
kind: feature
status: review
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
  - `run_isolation(app: &TestApp, fixtures) -> IsolationReport` that (1) lists tenant tables from the catalog (tables with an `account_id` column, plus `accounts` and the §2.1-excluded exceptions via a reasoned allow-list), (2) fails if any tenant table has no fixture and if any fixture names a non-tenant table, (3) creates accounts A and B (rows inserted through the fixture of `accounts` run first), (4) for each table inserts one row for A and one for B, (5) as A runs unfiltered `SELECT count(*)`, and runs `UPDATE … SET <touch column>` and `DELETE` only when the fixture declares those app-role mutations allowed; it asserts B's rows remain untouched and each allowed mutation affects one row, (6) asserts `relrowsecurity` and `relforcerowsecurity`, (7) asserts that an `INSERT` for B in A's context is rejected. The "touch column" is provided by the fixture (`touch_sql`) to avoid guessing column names.
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
pub struct IsolationFixture {
    pub table: &'static str,
    pub insert: InsertFn,
    pub touch_sql: &'static str,
    pub update_allowed: bool,
    pub delete_allowed: bool,
}
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

### Summary

Added the feature-gated PostgreSQL test kit with a shared cluster per integration-test binary, a fresh database per test, the three real database roles, migrations, and deterministic clock/ID/token generators. Added a catalog-driven tenant-isolation runner and self-tests, and migrated the database transaction and migration integration tests to use the kit.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `isolation_harness::test_db_is_isolated_per_test` | pass |
| AC-2 | `isolation_harness::pools_use_the_real_roles` | pass |
| AC-3 | `isolation_harness::sec_ac_01_probe_table_clean` | pass |
| AC-4 | `isolation_harness::tst_06_missing_fixture_is_a_finding` | pass |
| AC-5 | `isolation_harness::sec_ac_02_unforced_rls_is_a_finding` | pass |
| AC-6 | `isolation_harness::require_db_fails_in_ci` | pass |
| AC-7 | `migrate::*`, `tx::*` | pass (3 migration tests and 9 transaction tests) |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; eval "$(scripts/dev-postgres.sh env)"; NEXTEST_TEST_THREADS=1 cargo nextest run -p kinevo-db --features testing` | 0 | 26 passed, 0 skipped; exercised the external database URL path. |
| `export PATH="$HOME/.cargo/bin:$PATH"; env -u KINEVO_TEST_DATABASE_URL NEXTEST_TEST_THREADS=1 cargo nextest run -p kinevo-db --features testing` | 0 | 26 passed, 0 skipped; exercised the `testcontainers` PostgreSQL 17 path. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo clippy -p kinevo-db --all-targets --features testing --message-format=short -- -D warnings` | 0 | Passed. Existing `clippy.toml` entries still report unreachable-method warnings for `uuid::Uuid::new_v4` and `rand::thread_rng`. |
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo fmt --all; just check` | 0 | `just check`: 66 passed, 0 skipped; spec check: 0 findings in 60 files; contrast: 0 failures; task index: 0 problems in 132 tasks. `cargo-deny` exited 0 with duplicate-version and unmatched-license warnings. |
| `python3 scripts/spec_tool.py check` | 0 | `0 finding(s) in 60 spec files`. |
| `python3 scripts/tasks_index.py --check` | 0 | `0 problem(s) in 132 task(s)`. |

An earlier external-database run exited 100 because AC-5 expected the finding to name `FORCE`; the finding now names `FORCE ROW LEVEL SECURITY`, and both final database runs pass. The first post-edit `just check` exited 1 because rustfmt requested a line-wrap correction; after `cargo fmt --all`, the final gate above exits 0. The feature-specific Clippy command initially exited 101 on testkit and integration-test lint findings; those findings were corrected and the final command above exits 0.

### Files changed

Relative to the T-011 claim commit `966f743`; `Cargo.lock` is generated and excluded under D-55.

| Path | Added | Removed |
|---|---:|---:|
| `AGENTS.md` | 1 | 1 |
| `BRANCHING.md` | 18 | 30 |
| `WORKFLOW.md` | 11 | 7 |
| `crates/kinevo-db/Cargo.toml` | 9 | 0 |
| `crates/kinevo-db/src/lib.rs` | 3 | 0 |
| `crates/kinevo-db/src/testing/isolation.rs` | 360 | 0 |
| `crates/kinevo-db/src/testing/mod.rs` | 508 | 0 |
| `crates/kinevo-db/tests/isolation_harness.rs` | 212 | 0 |
| `crates/kinevo-db/tests/migrate.rs` | 46 | 205 |
| `crates/kinevo-db/tests/tx.rs` | 120 | 332 |
| `spec/decisions.md` | 3 | 0 |
| `spec/index.md` | 1 | 1 |
| `spec/standards/open-source-stack.md` | 1 | 0 |
| `tasks/T-011-db-test-kit-and-isolation-harness.md` | 92 | 3 |
| `tasks/index.md` | 2 | 2 |
| `Cargo.lock` | generated dependency updates | generated dependency updates |

Total changed lines excluding `Cargo.lock`: 1,968.

### Deviations

- D-58: used `testcontainers` 0.28 `GenericImage` instead of `testcontainers-modules`; version 0.15 resolves the core to 0.27, conflicting with the required 0.28 core dependency. Its readiness condition is PostgreSQL's explicit ready log, so no time-based sleep is used; the task's suggested `pg_isready` command probe is unavailable in the 0.28 `WaitFor` API.
- D-59: exposed `TestAppBuilder::clock_at(Instant)` from the task's Interface section because its Scope prose specifies a different `.clock(FixedClock::at(...))` form.
- Added the `futures` stack entry because the task's `BoxFuture` callback interface uses it.
- Updated the decision index, agent workflow and branching instructions to record maintainer decision D-57, which replaces the old D-54 milestone-branch text with the active `dev` workflow.
- The generated `Cargo.lock` changes are excluded from the budget. The non-lockfile diff is 1,968 changed lines, exceeding the 400-line guidance; D-55 makes that a recorded deviation, not a blocker.

### Decisions made by implementer

| ID | Choice and alternatives | Reason | Files changed | Reversal |
|---|---|---|---|---|
| D-58 | Use `GenericImage` with core 0.28 and wait for PostgreSQL's ready log; alternative: `testcontainers-modules` 0.15 / `pg_isready` wait. Pending owner review. | Module crate's core 0.27 conflicts with the required core 0.28; the log wait is an explicit condition without sleeps. | `spec/decisions.md`, `crates/kinevo-db/src/testing/mod.rs`, this task Report | Adopt a compatible modules release or add a supported command wait strategy, then update the testkit and decision log. |
| D-59 | Use `clock_at(Instant)`; alternative: a second `.clock(FixedClock)` overload. Pending owner review. | Matches the task's Interface section and avoids duplicate builder APIs. | `spec/decisions.md`, `crates/kinevo-db/src/testing/mod.rs`, this task Report | Change the builder signature and tests if the architect resolves the task wording differently. |

### Spec issues noticed

- T-011's Scope and Interface sections specify different clock builder signatures; recorded as D-59.
- T-011 requests `testcontainers-modules` with `testcontainers` 0.28, while the currently available module release resolves the core to 0.27; recorded as D-58.

### Not done / not verified

- No module-specific tenant fixtures exist yet; schema tasks add and register those fixtures. The current generated harness is self-tested against the T-010 probe table.
- PostgreSQL 18 was not run locally; the configured test default is PostgreSQL 17.
- No check was marked environment-limited; Docker and the throwaway external database both ran successfully.

### Follow-up suggestions

- Review D-58 and D-59 when the architect next updates the testing task and dependency map.

## Questions

- Q-1 (implementer, 2026-10-28): T-011 lists `testcontainers-modules` next to the required `testcontainers` 0.28 core, but module release 0.15 resolves core 0.27; the 0.28 `WaitFor` API does not provide a command probe for `pg_isready`.
  Resolved by implementer, pending owner review: use the core `GenericImage` and wait for PostgreSQL's ready log (D-58).
  Options: (a) downgrade the required core to 0.27; (b) use core 0.28 directly with a readiness-log condition.
  Recommendation: (b), because the task and stack both require core 0.28 and this avoids adding an incompatible dependency.
- Q-2 (implementer, 2026-10-28): the Scope prose gives `.clock(FixedClock::at(...))`, but the Interface section gives `clock_at(Instant)`.
  Resolved by implementer, pending owner review: implement the Interface signature (D-59).
  Options: (a) follow the Scope example; (b) follow the explicit Interface definition.
  Recommendation: (b), because the Interface defines the builder's public method and argument type.
