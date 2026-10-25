---
id: T-038
title: Add audit reading, the jobs summary, outbox retry and the platform prune jobs
milestone: M1
kind: feature
status: todo
depends_on: [T-034, T-033, T-013, T-014, T-015, T-012]
requirements: [ADM-FR-05, ADM-FR-07, ADM-FR-08, SEC-AC-10, API-14, D-41]
branch: m1-t038-admin-audit-jobs-and-prune
budget_lines: 380
assignee: ""
---

# T-038: Add audit reading, the jobs summary, outbox retry and the platform prune jobs

## Goal

Admins can read the audit log and a jobs summary and retry dead deliveries; owners can read their own audit entries; the platform tables are pruned by registered jobs.

## Context

> **Endpoints** (`spec/modules/admin.md` §2): `GET /admin/audit` (`admin.audit.read`; filters `action`, `from`, `to`, `account_id`; cursor page), `GET /account/audit` (`admin.audit.read_own`, own entries), `GET /admin/jobs/summary` (`admin.jobs.read`; counts by `kind`/`state`, oldest queued age, dead outbox deliveries), `POST /admin/outbox/{event_id}/{subscriber}/retry` (`admin.jobs.retry`; 202, `not_found`, `not_dead` 409).
> **ADM-FR-07**: the audit log is append-only for the application role; job `admin.audit.prune` (daily) deletes entries older than `audit.retention_days` through the SECURITY DEFINER function. **ADM-FR-08**: idempotency keys older than `api.idempotency.ttl` are pruned by `admin.idempotency.prune` (hourly). **§7**: `jobs.prune` (daily, finished jobs older than 7 days), `outbox.prune` (daily, delivered events older than 7 days), `outbox.dispatch` (continuous loop in the worker).
> **T-014 hand-off**: because `kinevo_app` cannot UPDATE deliveries, the retry endpoint enqueues a job `outbox.retry` `{event_id, subscriber}`; the worker handler calls `retry_dead` (T-014) in a `SystemTx`.
> **Audit details** hold ids, enums and counts only (ADM-FR-05); `GET` responses expose `details` as stored. **SEC-AC-10**: admin views contain no content fields.
> **Retention functions** (`06` §2.2): `platform_prune_audit`, `platform_prune_idempotency`, `platform_prune_jobs`, `platform_prune_outbox`, executed by `kinevo_system`; T-012/T-013/T-014/T-015 provide the Rust wrappers.
> **Pagination**: signed cursors (T-007), `limit` default 50, max 200.

## Scope

**In:**
- Audit read repositories: instance-wide (admin `Tx`) and own (`Tx::begin(account)`; the RLS already limits rows, the query adds `account_id = $1` as defence in depth).
- `GET /admin/jobs/summary` aggregation through `kinevo-db` read APIs.
- `POST /admin/outbox/{event_id}/{subscriber}/retry` enqueueing `outbox.retry`, plus the job handler.
- Job handlers `admin.audit.prune`, `admin.idempotency.prune`, `jobs.prune`, `outbox.prune` and their `JobSchedule` entries in the admin descriptor (daily/hourly cadence; schedule materialisation is the job queue's, T-013).

**Out:**
- Retry policy and backoff themselves: T-013/T-014.
- UI: T-053.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/app/audit.rs` | create | read use cases |
| `crates/kinevo-mod-admin/src/app/jobs_admin.rs` | create | summary, retry enqueue |
| `crates/kinevo-mod-admin/src/jobs.rs` | create | 4 prune handlers + `outbox.retry` |
| `crates/kinevo-mod-admin/src/http/audit.rs` | create | 2 audit routes |
| `crates/kinevo-mod-admin/src/http/jobs.rs` | create | summary + retry routes |
| `crates/kinevo-mod-admin/src/lib.rs` | modify | `jobs()` registers handlers |
| `crates/kinevo-mod-admin/tests/audit_jobs.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
// GET /admin/audit?action=&from=&to=&account_id=&cursor=&limit=  → 200 { items:[{ id, at, actor_id, account_id, action, details }], next_cursor }
// GET /account/audit                                             → same shape, own entries
// GET /admin/jobs/summary → 200 { jobs:[{ kind, state, count }], oldest_queued_age_seconds, dead_deliveries }
// POST /admin/outbox/{event_id}/{subscriber}/retry → 202 {}
pub fn jobs() -> Vec<JobHandlerSpec>;   // admin.audit.prune, admin.idempotency.prune, jobs.prune, outbox.prune, outbox.retry
```

## Business rules

1. Prune cutoffs: audit `now - audit.retention_days`; idempotency `now - api.idempotency.ttl`; jobs and outbox finished rows older than 7 days (a compiled constant named `FINISHED_RETENTION_DAYS`, not a policy, per §7).
2. Retrying a delivery that is not `dead` returns 409 `not_dead`; an unknown pair returns 404.
3. An owner never sees another account's audit rows, even by passing `account_id` (the filter is ignored on the own-entries route).
4. Each prune handler is idempotent and reports the deleted count in the job result.

## Security

- `GET /admin/audit` requires `admin.audit.read`; owners get 403 (SEC-AC-09 style).
- `details` is passed through unchanged; the PII scan test (ADM-FR-05) runs over every audit row written in this task's tests.
- The retry endpoint is `IdemMode::Optional` and writes audit `outbox.retried`.

## Implementation notes

- The summary query uses `GROUP BY kind, state` through a `kinevo-db` function; handlers never write SQL on platform tables (D-41).
- Cursor sort key is `(at, id)` descending.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** audit rows of two accounts **When** an admin lists with `account_id=A` **Then** only A's rows return, newest first, with a working `next_cursor` | `audit_jobs::adm_audit_admin_filters_and_pages` |
| AC-2 | **Given** an owner **When** `GET /account/audit` and `GET /admin/audit` **Then** own rows only; 403 on the admin route | `audit_jobs::adm_audit_owner_sees_only_own` |
| AC-3 | **Given** a dead delivery **When** `POST …/retry` **Then** 202 and an `outbox.retry` job; after the worker runs the delivery is pending again | `audit_jobs::outbox_retry_enqueues_and_resets` |
| AC-4 | **Given** a non-dead delivery **When** retried **Then** 409 `not_dead` | `audit_jobs::outbox_retry_not_dead` |
| AC-5 | **Given** old and new audit rows **When** `admin.audit.prune` runs **Then** only rows older than `audit.retention_days` go | `audit_jobs::adm_fr_07_prune_audit_by_policy` |
| AC-6 | **Given** `kinevo_app` **When** it tries `UPDATE`/`DELETE` on `audit_log` **Then** privilege error | `audit_jobs::adm_fr_07_app_role_cannot_modify_audit` |
| AC-7 | **Given** old idempotency rows **When** the prune job runs **Then** rows older than the TTL are removed | `audit_jobs::adm_fr_08_prune_idempotency` |
| AC-8 | **Given** queued, failed and dead rows **When** `GET /admin/jobs/summary` **Then** counts per kind/state and oldest age are correct and contain no payload fields | `audit_jobs::sec_ac_10_summary_has_no_content` |

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
