---
id: T-251
title: Add the operation executor port and the task and subtask executors
milestone: M5
kind: feature
status: todo
depends_on: [T-250]
requirements: [SYN-FR-04, SYN-FR-06, SYN-FR-08, TASK-FR-04, CA-10, CA-03]
branch: m5-t251-sync-operation-executors
budget_lines: 400
assignee: ""
---

# T-251: Add the operation executor port and the task and subtask executors

## Goal

Every supported offline operation runs through exactly the use case the online endpoint uses, with client-generated ids handled safely.

## Context

> **Supported ops** (`spec/modules/sync.md` §2): `task:create` (`POST /tasks`, `entity_id` client UUID), `task:update` (`PATCH`, needs `base_version`), `task:status` (user-actor transitions only, TASK-FR-04, needs `base_version`), `subtask:create` (`entity_id` client UUID, `payload.task_id`), `subtask:update` (needs `base_version`), `note:create`/`note:update` only when `knowledge` is enabled (executors arrive in M7). Anything else → `rejected` `op_unsupported` (422, `details.op_type`).
> **Port** (`kinevo-ports`, CA-03/CA-10): `OpExecutor<T>`: `op_types() -> &'static [&'static str]` and `execute(tx, op: &OpEnvelope, ctx: &OpCtx) -> OpOutcome` where `OpOutcome = Applied{http_status, entity} | Conflict{code, entity} | Rejected{code, details, http_status} | Retry{http_status}` (a transient error maps to `Retry`; the sync engine never inspects domain errors itself). Registered in the composition root; the sync module holds a registry keyed by `op_type` and skips executors whose module is disabled.
> **SYN-FR-04** `base_version` mismatch ⇒ `Conflict` `version_conflict` 409 with the server's current representation; illegal transition ⇒ `Conflict` `invalid_transition`. **SYN-FR-06** create ops carry `entity_id`: if it exists in the caller's account with an identical creation payload ⇒ `Applied` with replay semantics; different payload ⇒ `Rejected` `entity_id_taken` 409; if it collides with another account's row (invisible under RLS, so the insert hits the primary key) the result is the same `entity_id_taken` with no existence leak. **SYN-FR-08** ids of other accounts ⇒ `Rejected` `not_found` 404; archived workspace ⇒ `Rejected` `workspace_archived`.
> **Tasks module change**: the create use cases accept an optional caller-supplied id in the internal command (never in the HTTP DTO), and expose `TaskOps` executors that call the same functions as the handlers (validation schema included, unknown fields rejected). Executors map `AppError` to `OpOutcome` through the registry (`http_status` from `ErrorCode::http_status`).

## Scope

**In:**
- `OpExecutor` port, `OpEnvelope`, `OpOutcome`, fake executor behind `testing`; tasks-module executors for the five task/subtask ops; creation-payload equality check for replayed creates; wiring.

**Out:**
- Batch handling, endpoints and limits: T-252.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/sync_ops.rs` | create | port, types, fake |
| `crates/kinevo-mod-tasks/src/app/ops.rs, src/infra/op_executor.rs` | create |  |
| `crates/kinevo-mod-tasks/src/app/{create_task,subtasks}.rs` | modify | accept optional caller id internally |
| `crates/kinevo-mod-tasks/tests/op_executor.rs` | create | AC-1…AC-9 |
| `crates/kinevo-app/src/modules.rs` | modify | register executors |

## Interfaces

```rust
#[async_trait] pub trait OpExecutor<T: Send>: Send + Sync {
    fn op_types(&self) -> &'static [&'static str];
    async fn execute(&self, tx: &mut T, op: &OpEnvelope, ctx: &OpCtx) -> OpOutcome;
}
```

## Business rules

1. Executors never write the ledger; the engine does.
2. The user actor is the only actor available to executors.
3. A replayed create with an identical payload is `Applied` and creates nothing.

## Security

- Foreign ids and foreign workspace ids give `not_found` with no existence information.
- Payloads are validated by the target use case's schema; unknown fields rejected.

## Implementation notes

- Compare creation payloads by the normalised command (after defaults), not by the raw JSON.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** `task:create` with a client id **When** executed **Then** the task exists with that id and the online validation applied | `op_executor::syn_fr_06_create_with_client_id` |
| AC-2 | **Given** the same create again **When** executed **Then** `Applied` and no duplicate | `op_executor::syn_fr_06_identical_create_is_replay` |
| AC-3 | **Given** the same id with a different payload **When** executed **Then** `entity_id_taken` | `op_executor::syn_fr_06_different_payload_taken` |
| AC-4 | **Given** another account's task id in `task:update` **When** executed **Then** `not_found` | `op_executor::sec_ac_28_foreign_entity_not_found` |
| AC-5 | **Given** a stale `base_version` **When** `task:update` executes **Then** `Conflict` `version_conflict` with the current entity | `op_executor::syn_fr_04_version_conflict` |
| AC-6 | **Given** an illegal status change **When** `task:status` executes **Then** `Conflict` `invalid_transition`; a system-only target is rejected as for the user | `op_executor::syn_fr_04_invalid_transition` |
| AC-7 | **Given** an archived workspace **When** creating a task **Then** `Rejected` `workspace_archived` | `op_executor::syn_fr_08_archived_workspace` |
| AC-8 | **Given** a database error during execution **When** mapped **Then** `Retry` with 503/500 | `op_executor::syn_fr_03_transient_maps_to_retry` |
| AC-9 | **Given** an unknown field in the payload **When** executed **Then** `Rejected` validation with 422 | `op_executor::unknown_field_rejected` |

## Tests

```
cargo nextest run -p kinevo-mod-tasks --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
