---
id: T-012
title: Create the platform tables migration, retention functions and AuditWriter
milestone: M1
kind: feature
status: review
depends_on: [T-011, T-020]
requirements: [D-41, D-44, D-66, D-67, D-68, D-69, D-70, D-71, D-72, D-73, ADR-0011, DOM-26, DOM-09, SEC-10, SEC-04, ADM-FR-05, ADM-FR-07, SEC-AC-01, SEC-AC-02]
branch: m1-t012-db-platform-tables
budget_lines: 420
assignee: codex
---

# T-012: Create the platform tables migration, retention functions and AuditWriter

## Goal

The five platform tables exist with the exact schema, grants and RLS of the spec, audit rows can be appended from any context but never changed by application roles, and four retention functions can delete expired rows. A typed `AuditWriter` is the only way to write `audit_log`.

## Context

> **D-41(a)**: `audit_log`, `jobs`, `outbox_events`, `outbox_deliveries` and `idempotency_keys` are **platform** tables: created by `platform` migrations in `kinevo-db` and read/written only through `kinevo-db` APIs (`AuditWriter`, job queue, outbox, idempotency store).
> **D-44**: This migration is `crates/kinevo-db/migrations/20261101000200_platform_tables.sql`; it runs **after** the identity schema (`20261101000100_identity_accounts`) because the tables reference `accounts`.
> **Schema of record**: `spec/06-domain-model.md` §3 holds the `CREATE TABLE` statements for `audit_log`, `outbox_events`, `outbox_deliveries`, `jobs`, `idempotency_keys`; copy them exactly (including `audit_log.account_id ... ON DELETE SET NULL`, the `jobs UNIQUE (kind, unique_key)` and indexes `audit_log_by_account`, `outbox_deliveries_due`, `jobs_claim`).
> **§2.2 grants and policies**: `kinevo_app`: SELECT and INSERT on `audit_log` (never UPDATE/DELETE), SELECT and INSERT on `jobs`, `outbox_events`, `outbox_deliveries` (INSERT is the only write), DML on `idempotency_keys` except DELETE (RLS applies). `kinevo_system`: `outbox_events` SELECT, INSERT; `jobs` and `outbox_deliveries` SELECT, INSERT, UPDATE; `audit_log` INSERT. `audit_log` RLS: `USING (account_id = <current> OR current_setting('app.instance_admin', true) = 'on')`, `WITH CHECK (account_id IS NULL OR account_id = <current>)`, plus `system_insert FOR INSERT TO kinevo_system WITH CHECK (true)`, plus `retention FOR DELETE TO kinevo_migrator USING (true)`. `idempotency_keys` has the standard `tenant_isolation` policy plus the same `retention` policy. `jobs`, `outbox_events` and `outbox_deliveries` have no RLS (ids and enums only, DOM-09).
> **Retention functions**: four `SECURITY DEFINER` functions owned by `kinevo_migrator` — `platform_prune_audit(cutoff timestamptz)`, `platform_prune_idempotency(cutoff)`, `platform_prune_jobs(cutoff)` (finished jobs), `platform_prune_outbox(cutoff)` (delivered events and deliveries) — each returns the number of rows deleted; `EXECUTE` is granted to `kinevo_system` only and revoked from `PUBLIC`; `SET search_path = pg_catalog, public`.
> **Append-only trigger** (`06` §3): a trigger raises on UPDATE/DELETE of `audit_log` unless `current_user = 'kinevo_migrator'` (covers the retention function and the referential action that nulls `account_id`).
> **DOM-26**: Policy versions and audit rows are never updated or deleted by application roles.
> **SEC-10 / ADM-FR-05**: Every security-relevant action is appended to `audit_log` in the same transaction; `details` contain ids, enum values and counts only. Actions: setup completed, module switched, policy changed, secret set/deleted (name only), login success/failure, refresh reuse, account status changes, account deleted/exported, outbox retry, startup. Names used by the specs: `setup.completed`, `setup.token_failed`, `module.switched`, `policy.changed`, `secret.set`, `secret.deleted`, `auth.login`, `auth.login_failed`, `auth.refresh_reuse`, `auth.password_changed`, `account.status_changed`, `account.deleted`, `account.exported`, `outbox.retried`, `outbox.dead`, `startup`.
> **SEC-04**: Log and audit fields are ids, enums and counts; never emails, titles, tokens, prompts.

## Scope

**In:**
- The migration file described above, with a leading comment naming D-44.
- `kinevo_db::audit`: `AuditAction` enum (the 16 actions above; `as_str()` gives the dotted name), `AuditEntry { account_id: Option<AccountId>, actor_id: Option<Uuid>, action, target_type: Option<AuditTarget>, target_id: Option<String>, details: Details, request_id: Option<RequestId>, occurred_at: Instant }`, and `AuditWriter::record(&self, conn: &mut impl TxConn, entry: AuditEntry)` where `TxConn` is a sealed trait implemented for `Tx` and `SystemTx` (`fn conn(&mut self) -> &mut PgConnection`). The id comes from the injected `IdGen`. `details` reuses the kernel `Details` type (enums, ids, ints, bools only).
- `kinevo_db::retention`: `prune_audit`, `prune_idempotency`, `prune_jobs`, `prune_outbox` wrappers taking `&mut SystemTx` and a cutoff `Instant`.
- Isolation fixtures for `idempotency_keys` and `audit_log` registered in `crates/kinevo-db/tests/support/fixtures.rs` for use by the harness (T-011).

**Out:**
- Job queue API (T-013), outbox API (T-014), idempotency store (T-015). This task creates only the tables, grants and `AuditWriter`.
- The admin HTTP endpoints for audit (T-038).
- The prune **jobs** (T-038), which call the retention wrappers.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/migrations/20261101000200_platform_tables.sql` | create | |
| `crates/kinevo-db/src/audit.rs` | create | `AuditAction`, `AuditEntry`, `AuditWriter`, `TxConn` |
| `crates/kinevo-db/src/retention.rs` | create | wrappers |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod audit; pub mod retention;` and add the identity + platform sources to the test migrator list |
| `crates/kinevo-db/tests/platform_tables.rs` | create | component tests |
| `crates/kinevo-db/tests/support/fixtures.rs` | create | isolation fixtures |

## Interfaces

```rust
pub enum AuditAction { SetupCompleted, SetupTokenFailed, ModuleSwitched, PolicyChanged, SecretSet, SecretDeleted, AuthLogin, AuthLoginFailed,
                       AuthRefreshReuse, AuthPasswordChanged, AccountStatusChanged, AccountDeleted, AccountExported, OutboxRetried, OutboxDead, Startup }
impl AuditAction { pub fn as_str(self) -> &'static str; }   // "setup.completed" …
pub struct AuditWriter { ids: Arc<dyn IdGen> }
impl AuditWriter { pub async fn record(&self, tx: &mut impl TxConn, e: AuditEntry) -> Result<(), AppError>; }
pub async fn prune_audit(tx: &mut SystemTx, cutoff: Instant) -> Result<u64, AppError>;  // + prune_idempotency / prune_jobs / prune_outbox
```

## Business rules

1. `audit_log` is append-only for `kinevo_app` and `kinevo_system`: `UPDATE` and `DELETE` fail even for the row's own account.
2. Deleting an account row (as the migrator, i.e. the purge job through the FK action) keeps the audit rows and nulls `account_id`.
3. Retention functions delete only rows older than the cutoff; `jobs` only in states `succeeded`/`failed`/`dead`; `outbox_events` only when every delivery is `delivered` (or the event has none).
4. An account can read its own audit rows through `Tx::begin(account)` and no one else's; an admin context reads all (`app.instance_admin`).

## Security

- `details` is typed (`Details`): no free text can reach the table (SEC-04).
- `EXECUTE` on the retention functions is limited to `kinevo_system`; the functions pin `search_path`.
- The trigger plus missing grants give two independent layers against tampering (DOM-26).

## Implementation notes

- Use `uuid` ids from `IdGen`; `occurred_at` from the entry (the caller passes `clock.now()`); do not rely on `default now()`.
- `AuditWriter::record` is one `INSERT … VALUES ($1…)` with bound parameters; `details` is serialised by a small function in this file (`Details` → `jsonb`), which a unit test checks against a golden string.
- Add `details` size guard: more than 16 keys is a programming error (`debug_assert`) and is truncated in release builds by dropping nothing silently — return `internal_error` instead.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** the five tables are inspected **Then** columns, constraints and indexes match the spec DDL (compared by `information_schema` queries) | `platform_tables::dom_schema_matches_spec_ddl` |
| AC-2 | **Given** `kinevo_app` **When** it runs `UPDATE audit_log …` or `DELETE FROM audit_log` **Then** both fail with a privilege error; with `kinevo_system` too | `platform_tables::dom_26_audit_is_append_only_for_app_roles` |
| AC-3 | **Given** audit rows for account A **When** `Tx::begin(A)` reads **Then** only A's rows; `Tx::begin(B)` sees none of A's; `begin_admin` sees all | `platform_tables::adm_fr_05_audit_visibility` |
| AC-4 | **Given** `SystemTx` **When** it records `auth.login_failed` for account A **Then** the insert succeeds; **When** `Tx::begin(B)` records an entry for A **Then** it fails the RLS check | `platform_tables::adr_0011_system_insert_and_with_check` |
| AC-5 | **Given** an account with audit rows **When** the migrator deletes the account **Then** the rows remain with `account_id` null | `platform_tables::id_fr_11_audit_survives_account_purge` |
| AC-6 | **Given** old and new rows in each table **When** each retention function runs with a cutoff **Then** only expired rows are removed and the count is returned; `kinevo_app` cannot execute them | `platform_tables::adm_fr_07_retention_functions` |
| AC-7 | **Given** `jobs`, `outbox_*` and `idempotency_keys` **When** the isolation harness runs with the fixtures **Then** the report is clean (`idempotency_keys` is tenant-scoped; the three no-RLS system tables are listed in the harness' allow-list with their reasons, per D-66) | `platform_tables::sec_ac_01_isolation_harness_clean` |
| AC-8 | **Given** `AuditEntry` details with an id, an enum and a count **When** serialised **Then** the JSON equals the golden string | `audit::tests::details_serialise_canonically` |

## Tests

```
cargo nextest run -p kinevo-db --features testing   # environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes.
- [x] `just check` passes locally with PostgreSQL required in CI mode.
- [x] Files listed under *Files* and minimal wiring changes are recorded below; budget overage is non-blocking under D-55.
- [x] Report below is filled in.

## Report

### Summary

Added the platform migration for the five required tables, scoped grants and RLS, append-only audit trigger, four retention functions, `AuditWriter`, and retention wrappers. Expanded the database test setup and isolation fixtures so the combined identity/platform schema is tested with real PostgreSQL roles and migrations.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `platform_tables::dom_schema_matches_spec_ddl` | pass |
| AC-2 | `platform_tables::dom_26_audit_is_append_only_for_app_roles` | pass |
| AC-3 | `platform_tables::adm_fr_05_audit_visibility` | pass |
| AC-4 | `platform_tables::adr_0011_system_insert_and_with_check` | pass |
| AC-5 | `platform_tables::id_fr_11_audit_survives_account_purge` | pass |
| AC-6 | `platform_tables::adm_fr_07_retention_functions` | pass |
| AC-7 | `platform_tables::sec_ac_01_isolation_harness_clean` | pass |
| AC-8 | `audit::tests::details_serialise_canonically` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `export PATH="$HOME/.cargo/bin:$PATH"; cargo fmt --all` | 0 | formatted |
| `eval "$(scripts/dev-postgres.sh env)"; CI=true cargo nextest run -p kinevo-db --features testing --no-fail-fast` | 0 | 36 passed, 0 skipped; disposable PostgreSQL database was available |
| `eval "$(scripts/dev-postgres.sh env)"; CI=true cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing --no-fail-fast --no-capture` | 0 | 9 passed, 0 skipped; verifies identity fixtures against the combined platform schema |
| `eval "$(scripts/dev-postgres.sh env)"; CI=true just check` | 0 | formatting, Clippy, tests, cargo-deny and spec gates completed; 140 tests passed (11 slow), 0 skipped |
| `python3 scripts/spec_tool.py check` | 0 | `0 finding(s) in 60 spec files` |
| `python3 scripts/tasks_index.py --check` | 0 | `0 problem(s) in 132 task(s)` |
| `git diff --check` | 0 | no whitespace errors |

The first strict CI database run exited 101 with `database is required in CI: PostgreSQL test database is unavailable`; PostgreSQL's server log reported `permission denied to create extension "citext"` because the disposable test database lacked `CREATE` for `kinevo_migrator`. The test kit now grants that privilege before migrations, and the final strict database runs above pass. The first full platform run then exposed a retention-policy visibility issue and a generated index-name expectation; both were corrected and the final 36-test run passes.

### Files changed

- `crates/kinevo-db/migrations/20261101000200_platform_tables.sql`
- `crates/kinevo-db/src/audit.rs`, `src/retention.rs`, `src/lib.rs`
- `crates/kinevo-db/tests/platform_tables.rs`, `tests/support/fixtures.rs`, `tests/support/mod.rs`, `tests/support/migrations.rs`, `tests/migrate.rs`
- `crates/kinevo-db/src/isolation.rs`, `src/testing/isolation.rs`, `src/testing/mod.rs`, `tests/isolation_harness.rs`
- `crates/kinevo-mod-identity/tests/support/fixtures.rs`
- `crates/kinevo-db/Cargo.toml`, generated `Cargo.lock`
- `spec/06-domain-model.md`, `spec/decisions.md`, `spec/index.md`
- `tasks/T-011-db-test-kit-and-isolation-harness.md`, this task, generated `tasks/index.md`

### Deviations and environment

- No environment-limited checks. The disposable PostgreSQL instance was started by `scripts/dev-postgres.sh env` and tests ran with `CI=true`, so database setup errors fail instead of being counted as skips.
- `Cargo.lock` changed as generated dependency metadata; it is excluded from budget under D-55. The source diff is 1,969 changed lines against the 420-line guidance; D-55 makes that guidance non-blocking.
- T-012's AC-7 says four tables are no-RLS, but the higher-priority domain model and ADR-0011 require RLS on `audit_log` and `idempotency_keys`. D-66 records the correction: only `jobs`, `outbox_events`, and `outbox_deliveries` are in the no-RLS allow-list.
- Additional minimal wiring outside the Files table was needed to apply both migration sources, verify the revised retention policy, grant the test migrator database-level `CREATE`, roll back destructive isolation probes, and include platform fixtures in the combined identity test. These are tracked in D-71–D-73 and below.
- PostgreSQL names the unique index/constraint from the unqualified `UNIQUE (kind, unique_key)` clause `jobs_kind_unique_key_key`; the schema test checks that generated name.
- Clippy emitted the existing `clippy.toml` warning that `rand::thread_rng` is not a reachable function; the lint command and `just check` exited successfully.

### Decisions made by implementer

| ID | Choice and alternatives | Reason | Files changed | Reversal |
|---|---|---|---|---|
| D-66 | Follow the domain model: force RLS on `audit_log` and `idempotency_keys`; allowlist only the other three system tables. Rejected treating four platform tables as no-RLS. | T-012 AC-7 conflicts with `spec/06-domain-model.md` and ADR-0011. | `crates/kinevo-db/src/isolation.rs`, `src/testing/isolation.rs`, platform fixtures, spec and this task | Architect can revise the domain model and isolation contract in a superseding decision. Pending owner review. |
| D-67 | Use strict `< cutoff` against each lifecycle timestamp; `prune_outbox` returns delivery plus event rows. Rejected creation time for audit/idempotency and event-only counts. | Implements expiry and deleted-row count semantics for each table. | Platform migration, wrappers, tests, spec decision log | Update the function predicates and return contract together with an accepted decision. Pending owner review. |
| D-68 | Use `AuditTarget(&'static str)` with named constants. Rejected an extensible request string or a closed enum in `kinevo-db`. | Compile-time literals avoid arbitrary request text and avoid cross-module enum churn. | `crates/kinevo-db/src/audit.rs`, spec decision log | Replace the newtype and update callers if module-owned target registration changes. Pending owner review. |
| D-69 | Serialize only integer, static string, UUID and boolean values in sorted-key order; cap at 16 keys and return an internal error for unsupported values. Rejected dynamic text and silent truncation. | Keeps audit detail data within SEC-04 and deterministic golden output. | `crates/kinevo-db/src/audit.rs`, `Cargo.toml`, `Cargo.lock`, spec decision log | Revise the shared detail type and audit schema contract together. Pending owner review. |
| D-70 | Extend isolation fixtures with update/delete expectations and explicit no-RLS reasons; omit the app delete probe for append-only audit rows. Rejected unconditional mutation probes and undocumented exclusions. | Lets the verifier exercise tables with different grants while retaining exact RLS checks. | `crates/kinevo-db/src/isolation.rs`, `src/testing/isolation.rs`, fixtures, T-011 task, spec decision log | Change fixture policy fields and checker behavior together with an accepted isolation contract. Pending owner review. |
| D-71 | Constrain the idempotency DELETE policy by transaction-local cutoff and issue predicate-free `DELETE`. Rejected a migrator SELECT policy because it exposes stored response bodies. | PostgreSQL applies SELECT policies when a DELETE predicate reads table columns; strict tests showed the `USING (true)` policy with a `WHERE` clause pruned zero rows. | Platform migration, domain model, isolation checker, tests, spec decision log | Restore a broader SELECT policy or revise retention privileges with an accepted decision. Pending owner review. |
| D-72 | Roll back successful delete probes after asserting one tenant row was affected. Rejected committing probes that cascade away fixture parents. | Preserves the fixture graph for later child-table checks while testing delete authorization. | `crates/kinevo-db/src/testing/isolation.rs`, `tests/isolation_harness.rs`, spec decision log | Reorder fixture lifecycle or use isolated savepoints, then revise the harness. Pending owner review. |
| D-73 | Keep the combined global identity/platform migration test and add fixtures for the new platform tenant tables. Rejected removing the platform migration source from the identity suite. | Preserves migration ordering verification while making its isolation scan cover all migrated tenant tables. | `crates/kinevo-mod-identity/tests/support/fixtures.rs`, spec decision log | Move combined migration verification to another dedicated test and narrow the identity fixture suite. Pending owner review. |

### Not run / not verified

- No environment-limited commands. PostgreSQL tests, `just check`, the spec checker, and task index checker all ran.

## Questions

- **Q-1 — Resolved by implementer, pending owner review (D-66).** AC-7 says four of the platform tables have no RLS, while `spec/06-domain-model.md` §2.2/§3 and ADR-0011 require forced RLS on `audit_log` and `idempotency_keys`, leaving only three no-RLS tables. Follow the higher-priority domain model and record only the three system tables in the allow-list.
- **Q-2 — Resolved by implementer, pending owner review (D-71).** The specified `idempotency_keys` DELETE policy with `USING (true)` did not prune expired rows because PostgreSQL also applies SELECT policies when DELETE predicates read table columns. A migrator-only SELECT policy would expose response bodies. Use a transaction-local cutoff in the DELETE policy and a predicate-free DELETE so forced RLS remains tenant-safe and pruning works.
- **Q-3 — Resolved by implementer, pending owner review (D-72).** The generated delete probe committed and removed account or token-family fixture parents before later dependent-table checks. Roll back successful probes after asserting one row was affected, retaining the fixture graph while testing the app role's delete permission.
- **Q-4 — Resolved by implementer, pending owner review (D-73).** T-020's schema test applies the global platform migration source, so the new platform tenant tables appear in its isolation scan. Add audit and idempotency fixtures to T-020's fixture list while retaining the combined migration-order test.
