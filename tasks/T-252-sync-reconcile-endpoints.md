---
id: T-252
title: Implement POST /sync/reconcile, the result lookup, limits and the pruning job
milestone: M5
kind: feature
status: todo
depends_on: [T-251, T-018]
requirements: [SYN-FR-02, SYN-FR-03, SYN-FR-05, SYN-FR-07, SYN-FR-09, SYN-FR-10, SYN-FR-11, SEC-AC-27, SEC-AC-29, TST-07, TST-12, TST-19, API-73]
branch: m5-t252-sync-reconcile-endpoints
budget_lines: 420
assignee: ""
---

# T-252: Implement POST /sync/reconcile, the result lookup, limits and the pruning job

## Goal

A batch of queued operations reconciles safely: ordered, atomic per operation, replay-safe, bounded, and rate-limited, with module gating.

## Context

> **Endpoints** (`spec/modules/sync.md` §2, §6): `POST /sync/reconcile {protocol_version: 1, operations: [Op…]}` → 200 `{results: [Result…]}` in request order (always 200 for processed batches; per-op `http_status` carries semantics); `GET /sync/operations/{operation_id}` → stored `Result` or `not_found`. Errors before execution: `sync_protocol_unsupported` 422 (`details.supported`), `sync_batch_too_large` 422 (`details.max`), `sync_payload_too_large` 413 (`details.max_bytes`), `unauthenticated`, `rate_limited` 429 with `Retry-After`, `module_disabled` 404.
> **Op/Result** shapes as in the spec: `Op {operation_id, op_type, entity_id?, base_version?, payload, workspace_id?, client_created_at?}` (`client_created_at` informational, never used for ordering or decisions); `Result {operation_id, status: applied|conflict|rejected|retry, http_status, code?, replay, entity?, error?}`.
> **Flow** (§6): validate the envelope (protocol, sizes, shape) before any execution; for each op in order: if it depends on a failed entity record `rejected` `dependency_failed` (SYN-FR-05: a later op whose `entity_id`, `payload.task_id` or `payload.goal_id` references an entity that failed, or was created by a failed op); BEGIN account `Tx`; lookup the ledger (hit ⇒ replay or `operation_id_reused`, COMMIT); run the executor (T-251); insert the ledger row; COMMIT. A unique violation ⇒ ROLLBACK, re-read, replay. A transient error ⇒ ROLLBACK and `retry` with no ledger row.
> **Limits** (SYN-FR-07, policies `sync.batch.max_operations` 50, `sync.operation.max_bytes` 65536, `sync.request.max_bytes` 524288): the body size limit is enforced on the stream (SEC-AC-27: a 100 MB body or 10 000-op batch is rejected before the whole body is parsed). **SYN-FR-11** `ratelimit.sync_per_minute` per account for the whole request: exceeded ⇒ 429, nothing executed. **SYN-FR-09** module gating via the T-017 gate. **SYN-FR-10** job `sync.ledger.prune` (daily) deletes rows older than `sync.ledger.retention_days` (90). **API-73** an `Idempotency-Key` header together with a sync envelope is rejected (422 `validation_failed`).
> **SEC-AC-29** a replay storm of an `applied` op is always `replay=true` and constant work. **TST-19** fault injection per statement; **TST-07** the module matrix gains `sync` off.

## Scope

**In:**
- Engine `reconcile.rs`, handlers, DTOs, dependency tracking, prune job registration, rate-limit key, OpenAPI regeneration, matrix job entry.

**Out:**
- Client queue: T-253.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-sync/src/{app,infra,http}/reconcile.rs, lookup.rs, prune.rs` | create |  |
| `crates/kinevo-mod-sync/tests/{reconcile_api,limits,prune,module_off}.rs` | create | AC-1…AC-11 |
| `contracts/openapi.json, web/src/api/schema.d.ts` | modify | regenerated |
| `justfile or scripts/matrix.sh` | modify | add `sync` off to the module matrix |

## Interfaces

```rust
pub async fn reconcile(ctx: &Ctx, req: ReconcileRequest) -> Result<ReconcileResponse, AppError>;
```

## Business rules

1. Operations run strictly in array order, one transaction each.
2. Only `applied`, `conflict`, `rejected` are recorded; `retry` never is.
3. Nothing executes if any envelope check fails.
4. The HTTP status of a processed batch is 200.

## Security

- Size limits on the stream; per-account rate limit; ledger lookups are per account.
- Result entities respect the caller's permissions.

## Implementation notes

- The prune job runs in a `SystemTx` for enumeration only if needed; deletion by `created_at` is a single statement through the maintenance path used by other prune jobs (T-012 retention functions).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a batch **When** reconciled **Then** results arrive in request order with per-op `http_status` | `reconcile_api::syn_envelope_order_and_statuses` |
| AC-2 | **Given** 20 parallel identical ops **When** run **Then** one execution and 19 replays | `reconcile_api::syn_fr_02_parallel_identical_ops` |
| AC-3 | **Given** a database failure mid-op **When** reconciled **Then** `retry`, no ledger row, and the resend succeeds | `reconcile_api::syn_fr_03_transient_not_recorded` |
| AC-4 | **Given** a failed `task:create` **When** its `subtask:create` follows **Then** `dependency_failed`, recorded | `reconcile_api::syn_fr_05_dependency_failed` |
| AC-5 | **Given** limits at and over their boundaries **When** posted **Then** exact error codes and nothing executed | `limits::syn_fr_07_boundaries` |
| AC-6 | **Given** a 100 MB body **When** posted **Then** rejected without buffering it whole | `limits::sec_ac_27_stream_size_limit` |
| AC-7 | **Given** the rate limit exceeded **When** posted **Then** 429 with `Retry-After` and nothing executed | `limits::syn_fr_11_rate_limit` |
| AC-8 | **Given** `sync` off **When** `/sync/*` is called **Then** 404 `module_disabled` | `module_off::syn_fr_09_module_gating` |
| AC-9 | **Given** old rows **When** the prune runs with an injected clock **Then** only rows older than retention are deleted | `prune::syn_fr_10_retention` |
| AC-10 | **Given** a replay storm **When** run **Then** every response is `replay=true` | `reconcile_api::sec_ac_29_replay_storm` |
| AC-11 | **Given** failure injected at each statement **When** an op runs **Then** no half state | `reconcile_api::tst_19_fault_injection` |

## Tests

```
cargo nextest run -p kinevo-mod-sync --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
