---
id: T-012
title: Create the platform tables migration, retention functions and AuditWriter
milestone: M1
kind: feature
status: in_progress
depends_on: [T-011, T-020]
requirements: [D-41, D-44, ADR-0011, DOM-26, DOM-09, SEC-10, SEC-04, ADM-FR-05, ADM-FR-07, SEC-AC-01, SEC-AC-02]
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
| AC-7 | **Given** `jobs`, `outbox_*` and `idempotency_keys` **When** the isolation harness runs with the fixtures **Then** the report is clean (`idempotency_keys` is tenant-scoped; the other four are listed in the harness' no-RLS allow-list with their reasons) | `platform_tables::sec_ac_01_isolation_harness_clean` |
| AC-8 | **Given** `AuditEntry` details with an id, an enum and a count **When** serialised **Then** the JSON equals the golden string | `audit::tests::details_serialise_canonically` |

## Tests

```
cargo nextest run -p kinevo-db --features testing   # environment-limited without Docker/PostgreSQL
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
