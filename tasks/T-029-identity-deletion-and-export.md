---
id: T-029
title: Implement account deletion (purge job) and data export (ZIP builder)
milestone: M1
kind: feature
status: todo
depends_on: [T-028, T-013, T-014, T-008]
requirements: [ID-FR-10, ID-FR-11, ID-FR-12, MOD-11, SM-21, DOM-04, SEC-10, D-41]
branch: m1-t029-identity-deletion-export
budget_lines: 430
assignee: ""
---

# T-029: Implement account deletion (purge job) and data export (ZIP builder)

## Goal

Users can delete their account (after password confirmation) and export their data as a ZIP. Deletion removes all tenant data through the database cascade while keeping anonymised audit rows. Export assembles `data.json` from every module's `AccountExporter` and never contains secrets.

## Context

> **Endpoints** (`identity.md` §2): `DELETE /account` `{password, confirm: "delete my account"}` (`identity.account.delete`) → 202; state `deleting`; sessions revoked; errors `current_password_incorrect` 422, `last_admin` 409. `POST /account/export` (`identity.account.export`) → 202 `{ export_id }`; `export_in_progress` 409 (`details.export_id`). `GET /account/export/{id}` → 200 `{ state, size_bytes?, expires_at }`; `not_found`. `GET /account/export/{id}/download` → 200 `application/zip`; `export_not_ready` 409, `export_expired` 410.
> **ID-FR-11**: After password confirmation, status becomes `deleting`, all sessions are revoked, and a job `account.purge` deletes the account row (cascade removes all tenant data) within `identity.purge.delay` (0–30 d, default 0). Audit rows keep `account_id` nulled by the foreign key and retain only the action. Journey J7.
> **ID-FR-10**: An account cannot delete itself if it is the last active `instance_admin` (`last_admin`).
> **State machine — Account status** (`identity.md` §4): `active → deleting` (owner deletes: password ok, not last admin; job fails → retried; stays `deleting` and unusable; admin sees a stuck count); `deleting → row deleted` (purge job).
> **ID-FR-12**: Data export produces a ZIP with `data.json` (all account data, schema `export_v1`, assembled from every module's `AccountExporter`, MOD-11), `landscape.ics`, and `README.txt` explaining the format. Secrets and password hashes are excluded. Archives expire after `export.max_age_days` (1–30, default 7).
> **Export format** (`contracts.md` §8): `kinevo-export-<account-uuid>-<YYYYMMDD>.zip` containing `README.txt`, `data.json` (`{schema:"export_v1", exported_at, account:{profile}, workspaces[], goals[], …}`), `landscape.ics`, `canvas-files/…`. Excluded: password hash, tokens, TOTP seed, recovery codes, API keys, audit log of other accounts, instance data. Ids preserved; timestamps canonical instants. ZIP: deflate, UTF-8 names, no absolute paths, no `..`.
> **MOD-11**: sections come from `AccountExporter`s of every compiled module, enabled or not; a section name may be claimed by one exporter only (startup check, T-008). In M1 only identity contributes the `account` object; later modules add arrays. `landscape.ics` and `canvas-files/` are added by their modules' milestones (the exporter contract is extended with files then); in M1 the ZIP contains `README.txt` and `data.json` only, and the README says which parts exist.
> **SM-21 Account export**: `(none) → queued` (second request while active ⇒ `export_in_progress`); `queued → running` (worker picks; crash ⇒ lease expiry ⇒ `queued`); `running → ready` (bytes, `expires_at`; error ⇒ `failed`, user may request again); `ready → expired` (`expires_at` elapsed; bytes deleted; download ⇒ `export_expired`).
> **Schema**: `account_exports(id, account_id, state in queued|running|ready|failed|expired, bytes bytea, size_bytes, created_at, expires_at)`.
> **Jobs**: `account.purge` (payload `{account_id}`), `account.export.build` (payload `{export_id}`), `identity.exports.prune` (daily). Handlers run with the worker: they load the account context with `Tx::begin(account_id)` (purge deletes the row inside `Tx::begin(account_id)`; `tenant_isolation` on `accounts` allows it, D-45).
> **Stack**: the `zip` crate (MIT) is **not** in the stack map: add a row (purpose: export archives; deflate only; no extraction code paths) in this task, CQ-16. Alternative considered: hand-written store-only ZIP writer (no compression) — rejected as more code than the dependency.
> **Events/audit**: `AccountDeletionRequested` event; audit `account.deleted` is written at purge time **before** the delete, in the same transaction and with the account id; the foreign key then nulls `account_id` (`SET NULL`), leaving the action and details. Audit `account.exported` when the export becomes `ready`.

## Scope

**In:**
- Deletion: `app::deletion::request_deletion(account, password, confirm)`: verifies the password and the exact, case-sensitive confirm phrase `delete my account` (a wrong phrase is a 422 `validation_failed` with a field error `enum_invalid` at path `confirm`), checks `can_change_status` for last admin (T-028 pure function), then in one `Tx`: set status `deleting`, revoke all families with reason `logout`, enqueue job `account.purge` with `run_at = now + identity.purge.delay` and `unique_key = account_id`, emit `AccountDeletionRequested`, audit `account.status_changed`. Responds 202.
- `jobs::account_purge` handler: re-checks `status = deleting` (idempotent: row already gone → success), writes audit `account.deleted` (details `{}`), `DELETE FROM accounts WHERE id = $1`. Failure keeps the account `deleting` and the job retries with backoff (T-013).
- Export: `app::export::request_export` (creates a `queued` row unless a `queued|running` one exists → `export_in_progress`; enqueues `account.export.build`), `status`, `download` (checks state/expiry; streams bytes with `Content-Type: application/zip`, `Content-Disposition: attachment; filename="kinevo-export-<uuid>-<date>.zip"`, `Cache-Control: no-store`).
- `jobs::export_build`: `Tx::begin(account)`; marks `running`; loads the profile (`ExportProfile` excludes password hash, TOTP flag only as boolean `totp_enabled`); collects sections via the injected `Vec<Arc<dyn AccountExporter<Tx>>>`; builds `data.json` with **sorted keys** and stable section order (identity's `account` first, then exporters in the injected order) so output is deterministic; writes `README.txt` (format version, part list, how to read ids/timestamps); zips with the `zip` crate; sets `ready`, `size_bytes`, `expires_at = now + export.max_age_days`; audit `account.exported`. Errors → `failed`.
- `jobs::exports_prune`: the system role has **no grant** on `account_exports` (T-020, SEC-06), so pruning is per account: when an export becomes `ready` the build job enqueues `identity.exports.prune` with payload `{account_id, export_id}`, `unique_key = export_id` and `run_at = expires_at`; the handler opens `Tx::begin(account_id)`, sets `expired` and nulls `bytes`. The status and download paths also treat a `ready` export past `expires_at` as `expired` (lazy expiry) so behaviour is correct even before the job runs. Document this design in the Report.
- Handlers and `RouteSpec`s for the five endpoints, with permissions above, `RateClass::Export` for `POST /account/export` and download, `Api` otherwise.
- A placeholder exporter in identity (`IdentityExporter`) contributing section `account`.

**Out:**
- Exporters for other modules (their milestones), `landscape.ics`, canvas files.
- Web screens (T-051).
- Import of exports (not in 1.0).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/app/{deletion,export}.rs` | create | |
| `crates/kinevo-mod-identity/src/app/jobs.rs` | modify | `account.purge`, `account.export.build`, `identity.exports.prune` handlers |
| `crates/kinevo-mod-identity/src/infra/exports.rs` | create | repository |
| `crates/kinevo-mod-identity/src/domain/export.rs` | create | state machine `transition`, README text, file naming |
| `crates/kinevo-mod-identity/src/http/deletion.rs`, `http/export.rs` | create | |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | routes, `jobs()`, `exporters()` |
| `crates/kinevo-mod-identity/tests/deletion.rs`, `tests/export.rs` | create | |
| `crates/kinevo-mod-identity/Cargo.toml` | modify | `zip` (deflate only, default-features off) |
| `spec/standards/open-source-stack.md` | modify | `zip` row |

## Interfaces

```rust
pub fn transition(state: ExportState, event: ExportEvent, now: Instant, expires_at: Option<Instant>) -> Result<ExportState, TransitionError>;  // SM-21, table-tested
pub struct ExportSection { pub name: &'static str, pub rows: serde_json::Value }   // from kinevo-ports (T-008)
pub fn export_file_name(account: AccountId, date: LocalDateStamp) -> String;   // kinevo-export-<uuid>-<YYYYMMDD>.zip
```

## Business rules

1. A wrong password → 422 `current_password_incorrect`; a wrong confirmation phrase → 422 validation error at `confirm`; the last active admin → 409 `last_admin`; no state changes in any failure.
2. After a successful request the account is `deleting`: every API call returns 403 `account_disabled`; login returns `account_disabled`.
3. With `identity.purge.delay = 0` the purge job is due immediately; with a delay it runs at `now + delay`.
4. Purge removes every tenant row (the isolation harness lists all tenant tables; the test checks each is empty for the account) and leaves audit rows with `account_id` null.
5. Only one active export per account; a `failed` or `expired` one allows a new request.
6. `data.json` contains no key named `password_hash`, `token_hash`, `ciphertext`, `totp_seed`, `recovery`, `api_key` at any depth (scan test).
7. Download of a non-`ready` export → 409 `export_not_ready`; of an expired one → 410 `export_expired`; a foreign export id → 404.
8. The ZIP contains only the expected entries with safe names; the archive is deterministic for equal inputs and fake clock (entry timestamps are set from the injected clock).

## Security

- Export bytes are S3 at rest: stored in `account_exports.bytes` under RLS, deleted at expiry; downloads require the owner's bearer token; never logged.
- The export builder uses `Tx::begin(account)`, so it can only read the owner's rows; exporters receive the same `Tx`.
- Deletion requires password re-verification; the phrase prevents accidental clicks (not a security control).
- The purge handler never trusts the payload beyond the account id and re-checks status `deleting`.

## Implementation notes

- ZIP timestamps: `zip::DateTime` from the injected clock; no system time.
- `data.json` is serialised from typed structs; `serde_json` with a `BTreeMap` for the top-level and sections to keep a stable order. JCS (RFC 8785) is not required here (not hashed).
- The prune design avoids granting the system role any new table (SEC-06); keep it that way.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an owner with sessions **When** `DELETE /account` with the right password and phrase **Then** 202, status `deleting`, all families revoked, a purge job queued at `now + delay`, event emitted | `deletion::id_fr_11_request_flow` |
| AC-2 | **Given** wrong password, wrong phrase, last admin **When** deleting **Then** 422, 422 (field `confirm`), 409 `last_admin`, and the account is unchanged | `deletion::id_fr_10_failures_change_nothing` |
| AC-3 | **Given** a `deleting` account **When** it calls the API or logs in **Then** 403 `account_disabled` | `deletion::deleting_account_is_locked_out` |
| AC-4 | **Given** an account with a row in every identity tenant table plus fixture rows from the isolation fixtures **When** the purge job runs **Then** the account row and all rows are gone, audit rows remain with `account_id` null and the `account.deleted` action | `deletion::id_fr_11_purge_cascades_and_keeps_audit` |
| AC-5 | **Given** the purge handler run twice **When** the second finds no row **Then** it succeeds (idempotent) | `deletion::purge_is_idempotent` |
| AC-6 | **Given** the export state machine **When** table-tested over all (state × event) pairs **Then** transitions equal SM-21 | `export::sm_21_state_machine_table` |
| AC-7 | **Given** a request **When** a second arrives while `queued|running` **Then** 409 `export_in_progress` with `details.export_id` | `export::id_fr_12_single_active_export` |
| AC-8 | **Given** two fake exporters adding sections **When** the build job runs **Then** the ZIP has `README.txt` and `data.json` with `schema = "export_v1"`, the `account` object and both sections; entry names are safe; a duplicate section name fails at startup validation | `export::mod_11_sections_from_all_exporters` |
| AC-9 | **Given** the built `data.json` **When** scanned at every depth **Then** none of the forbidden keys appear and the profile has no password hash | `export::id_fr_12_no_secrets_in_export` |
| AC-10 | **Given** `ready` and `expired` exports **When** downloaded **Then** 200 `application/zip` with attachment headers, and 410 `export_expired` (bytes null) | `export::download_states` |
| AC-11 | **Given** the same inputs and fake clock **When** the build runs twice **Then** the ZIP bytes are identical | `export::deterministic_archive` |
| AC-12 | **Given** another account's export id **When** requested **Then** 404 | `export::sec_07_foreign_export_is_404` |

## Tests

```
cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] `zip` is recorded in the stack map (CQ-16).
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
