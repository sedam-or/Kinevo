---
id: T-033
title: Implement the policies service, PolicyReader and the policies endpoints
milestone: M1
kind: feature
status: review
depends_on: [T-031, T-006, T-008, T-012, T-014, T-017, T-019]
requirements: [POL-01, POL-02, POL-03, POL-04, POL-05, POL-08, ADM-FR-04, ADM-FR-05, D-39]
branch: m1-t033-admin-policies-endpoints
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-033: Implement the policies service, PolicyReader and the policies endpoints

## Goal

Policies resolve through one service (defaults, instance rows, account rows, effective dating) behind the `PolicyReader` port, and can be listed and changed over HTTP with validation, audit and invalidation.

## Context

> **POL-02** (`spec/04-policies.md`): `GET /policies?scope=` returns resolved values with `source` (`default`/`instance`/`account`), `version_id`, `effective_from`, plus the declared type, bounds and label key.
> **POL-03**: `PUT /policies/{key}` body `{ value, effective_from?, scope }` validates type and bounds → 422 `policy_value_invalid` (`details.bounds`); unknown key 404 `policy_unknown`; wrong scope 403 `forbidden`; scope not allowed for key 422 `policy_scope_invalid`.
> **POL-04**: `effective_from` defaults to now. `future_only` keys reject past values (`policy_effective_in_past`). `immediate` keys must be ≥ the latest version's `effective_from` for that scope (`policy_effective_order`).
> **POL-05**: A change writes audit `policy.changed` (key, old/new value, scope) and outbox `PolicyChanged`; caches invalidate on the event.
> **POL-08**: Bounds are enforced again on load; an out-of-bounds stored value resolves to the compiled default and logs `policy_out_of_bounds` once per key per process (no value in the log).
> **ADM-FR-04**: Account-scope writes require the account's own context; instance-scope writes require `instance_admin`.
> **Storage**: `policy_versions` (T-031), append-only; resolution picks the latest version with `effective_from <= at`, account row over instance row over default (T-006 `resolve`).

## Scope

**In:**
- `PolicyService` loading versions for a key set, caching resolved snapshots in `moka` (TTL `modules.cache_ttl_seconds`-independent: policy cache TTL is a compiled constant of 30 s plus invalidation by the `PolicyChanged` event).
- `PolicyReader` implementation for other modules (`get`, `snapshot`).
- `GET /policies`, `PUT /policies/{key}` with the permission checks of ADM-FR-04, audit and outbox in the same transaction.
- Registry listing metadata (type, bounds, label key) generated from the kernel registry.

**Out:**
- Which keys exist: the kernel registry (T-006) and later module tasks.
- UI: T-051/T-053.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/app/policies.rs` | create | service, validation, writes |
| `crates/kinevo-mod-admin/src/infra/policy_repo.rs` | create | queries over `policy_versions` |
| `crates/kinevo-mod-admin/src/infra/policy_reader.rs` | create | `PolicyReader` impl + cache |
| `crates/kinevo-mod-admin/src/http/policies.rs` | create | handlers, DTOs, route specs |
| `crates/kinevo-mod-admin/tests/policies.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub struct PolicyService { /* db, cache, audit, outbox, clock, ids */ }
impl PolicyService {
    pub async fn list(&self, ctx: &AuthCtx, scope: Scope) -> Result<Vec<PolicyView>, AppError>;
    pub async fn set(&self, ctx: &AuthCtx, key: &str, scope: Scope, value: serde_json::Value, effective_from: Option<Instant>) -> Result<PolicySet, AppError>;
}
// GET /policies?scope=instance|account → 200 { items:[{ key, type, bounds, label_key, source, value, version_id, effective_from }] }
// PUT /policies/{key}                  → 200 { version_id, effective_from }
```

## Business rules

1. Instance scope read is allowed to both roles; instance write only to `instance_admin` (otherwise `forbidden`).
2. The new row stores the validated value in canonical JSON; the old value is read inside the same transaction for the audit entry.
3. A write with an identical value and scope as the current resolved value still appends a version (history is the record), except during template application (TPL-04, T-035), which skips unchanged values.
4. The cache is invalidated on commit of a write and on the `PolicyChanged` event for other nodes.

## Security

- Audit `details` hold key, scope and old/new value only for non-secret keys; no policy is secret (POL-01).
- Account-scope writes use `Tx::begin(account)`, instance-scope writes use `Tx::begin_admin(account)`; an owner cannot reach instance writes through a crafted `scope` field.
- Unknown keys never echo user input in the problem body.

## Implementation notes

- Validation lives in the kernel (T-006); this task only wires it.
- `policy_out_of_bounds` logging uses a `OnceMap` keyed by policy key; log fields are key name and nothing else.
- List output order is the registry order for stability.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** defaults only **When** `GET /policies?scope=account` **Then** every account key has `source=default` | `policies::pol_02_list_resolved_with_metadata` |
| AC-2 | **Given** an admin **When** `PUT` a valid instance value **Then** 200, one new version, one `policy.changed` audit row and one `PolicyChanged` outbox row | `policies::pol_05_write_audits_and_emits` |
| AC-3 | **Given** an owner **When** `PUT` with `scope=instance` **Then** 403 `forbidden` | `policies::adm_fr_04_owner_cannot_write_instance` |
| AC-4 | **Given** a value out of bounds **When** `PUT` **Then** 422 `policy_value_invalid` with `details.bounds` | `policies::pol_03_value_invalid_has_bounds` |
| AC-5 | **Given** an unknown key **When** `PUT` **Then** 404 `policy_unknown`; a key not allowed at the scope gives 422 `policy_scope_invalid` | `policies::pol_03_unknown_key_and_scope` |
| AC-6 | **Given** a `future_only` key **When** `effective_from` is in the past **Then** 422 `policy_effective_in_past`; an `immediate` key earlier than the latest version gives `policy_effective_order` | `policies::pol_04_effective_rules` |
| AC-7 | **Given** a stored value outside current bounds **When** resolved **Then** the compiled default is returned and the warning is logged once | `policies::pol_08_out_of_bounds_uses_default_logs_once` |
| AC-8 | **Given** a cached value **When** a write commits **Then** the next read sees the new value | `policies::cache_invalidated_on_write` |

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

### Summary

The admin module now resolves policy defaults and stored versions through `PolicyReader`, exposes resolved policy metadata and validated writes over HTTP, and records writes with audit and outbox events. Policy writes invalidate the local cache after commit, while `PolicyChanged` events invalidate cached snapshots on other nodes; out-of-bounds stored values fall back to defaults and log once per key.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `policies::pol_02_list_resolved_with_metadata` | pass |
| AC-2 | `policies::pol_05_write_audits_and_emits` | pass |
| AC-3 | `policies::adm_fr_04_owner_cannot_write_instance` | pass |
| AC-4 | `policies::pol_03_value_invalid_has_bounds` | pass |
| AC-5 | `policies::pol_03_unknown_key_and_scope` | pass |
| AC-6 | `policies::pol_04_effective_rules` | pass |
| AC-7 | `policies::pol_08_out_of_bounds_uses_default_logs_once` | pass |
| AC-8 | `policies::cache_invalidated_on_write` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && df -h / && cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing --test policies` | 0 | 8 passed, 0 skipped. Disk was 62 GB free before and after PostgreSQL setup. |
| `df -h / && eval "$(scripts/dev-postgres.sh env)" && df -h / && just check` | 0 | `cargo fmt`, Clippy and all gates passed; workspace nextest 316 passed, 1 intentionally ignored 200-sample timing benchmark (`just test-slow`); cargo-deny advisories, bans, licenses and sources OK; spec check 0 findings/60 files; contrast failures 0; task index 0 problems/132 tasks. Disk was 62 GB free before and after PostgreSQL setup. |

Clippy reported the existing `rand::thread_rng` entry in `clippy.toml` as unreachable. Cargo-deny reported existing duplicate crate entries and unmatched license allowances; none failed a gate.

### Files changed

`Cargo.lock` adds 1 generated line and is excluded from the D-55 budget. Excluding that lockfile, the task changes 1,584 lines added and 13 removed.

| Path | Added | Removed |
|---|---:|---:|
| `crates/kinevo-http/src/route.rs` | 8 | 0 |
| `crates/kinevo-mod-admin/Cargo.toml` | 3 | 2 |
| `crates/kinevo-mod-admin/src/app/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/app/policies.rs` | 313 | 0 |
| `crates/kinevo-mod-admin/src/http/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/http/policies.rs` | 134 | 0 |
| `crates/kinevo-mod-admin/src/infra/mod.rs` | 6 | 0 |
| `crates/kinevo-mod-admin/src/infra/policy_reader.rs` | 182 | 0 |
| `crates/kinevo-mod-admin/src/infra/policy_repo.rs` | 219 | 0 |
| `crates/kinevo-mod-admin/src/lib.rs` | 15 | 6 |
| `crates/kinevo-mod-admin/tests/policies.rs` | 612 | 0 |
| `crates/kinevo-ports/src/platform.rs` | 8 | 0 |
| `tasks/T-033-admin-policies-endpoints.md` | 76 | 3 |
| `tasks/index.md` | 2 | 2 |

### Deviations

- Added small wiring/dependency changes outside the task's five listed new files: `crates/kinevo-http/src/route.rs` to merge module route tables, `crates/kinevo-ports/src/platform.rs` for policy-change subscription, and admin module exports/descriptor registration. Added `moka` (already approved in `spec/standards/open-source-stack.md`) to the admin crate and regenerated `Cargo.lock`.
- The diff exceeds the approximate 400-line guidance because the service, repository, cache, HTTP layer and eight database acceptance tests are new; budget size is guidance, not a blocker under D-55.
- The current kernel registry has no account-scoped or `future_only` key. No business policy was invented; the service effective-date test uses a synthetic policy specification to cover the rule.
- Used `admin.policy.<key>` as the stable label-key convention because the task/spec do not define a label-key naming format.
- Audit writes include the prior and new JSON values only for registry-known non-secret policies, as the task's Security section requires. This follows the task's explicit policy-specific allowance and is recorded below for architect review against the broader audit detail constraints.

### Spec issues noticed

- The current registry contains no account-scoped or `future_only` policies, so the default account listing acceptance does not exercise a populated account-policy set. Later policy-registry work should add such entries only with approved policy values.
- The task asks for `label_key` metadata but does not define how label keys are formed; this implementation uses `admin.policy.<key>`.

### Not done / not verified

No environment-limited checks. The ignored 200-sample password-hasher timing benchmark was not run; `just test-slow` remains available for it. No browser, Android, signing-key or 1.0 release checks are in scope for this task.

### Follow-up suggestions

Architect review of the two implementer resolutions below and confirmation of the label-key convention before policy labels are localized.

## Questions

- Q-1 (implementer, 2026-10-29): Neither the task nor the cited policy spec defines the `label_key` naming convention.
  Options: (a) use a stable `admin.policy.<key>` localization key; (b) add a label field to every policy registry entry.
  Recommendation: (a), because it needs no new policy data or business decision.
  Resolved by implementer, pending architect review: this implementation uses `admin.policy.<key>`.
- Q-2 (implementer, 2026-10-29): The task's POL-05 and Security sections require audit old/new policy values for non-secret keys, while the broader audit detail rules constrain dynamic detail values.
  Options: (a) retain JSON old/new values for registry-known non-secret policies; (b) record only key, scope and value types/counts.
  Recommendation: (a), because it follows this task's explicit policy-specific requirement and POL-01 marks registered policies non-secret.
  Resolved by implementer, pending architect review: retain old/new JSON values for registered non-secret policies; no values are logged.
