---
id: T-250
title: Create the sync module: migration, descriptor, canonical payload hash and the ledger store
milestone: M5
kind: feature
status: todo
depends_on: [T-207]
requirements: [SYN-FR-01, SYN-FR-02, SYN-FR-03, DOM-20, D-44, D-34, D-35, SEC-AC-01, SEC-AC-25, SEC-AC-26]
branch: m5-t250-sync-schema-and-ledger
budget_lines: 380
assignee: ""
---

# T-250: Create the sync module: migration, descriptor, canonical payload hash and the ledger store

## Goal

The server can record and replay operation outcomes exactly once per account, with a canonical payload hash that detects id reuse.

## Context

> **Module** (`spec/modules/sync.md`): kind **toggleable**, default on; depends on `tasks`; optional `knowledge`; table `offline_operations`; permissions `sync.reconcile.execute`, `sync.ledger.read` for `account_owner` and `instance_admin`.
> **DDL** (`06-domain-model.md` §8): `offline_operations(id, account_id, operation_id uuid, op_type, payload_hash bytea, outcome in (applied, conflict, rejected) as op_outcome_ck, outcome_code, result jsonb, created_at, UNIQUE (account_id, operation_id))`, index `offline_ops_prune (created_at)`, standard RLS. Migration `crates/kinevo-mod-sync/migrations/20270301000000_sync_sync.sql`.
> **SYN-FR-01** hash input = JCS (RFC 8785) of `{ "op_type", "entity_id", "base_version", "payload", "workspace_id" }` with absent optionals omitted (`standards/contracts.md` §5), hashed with SHA-256 (`hash_token` from the kernel crypto module). An existing row with the same hash ⇒ the stored result with `replay=true`; a different hash ⇒ `rejected`, `operation_id_reused` 422. **SYN-FR-02** the ledger insert and the use-case execution share one transaction; the loser of a concurrent insert re-reads and replays. **SYN-FR-03** only `applied`, `conflict`, `rejected` are recorded; transient failures are never recorded.
> **Canonical JSON**: use `serde_jcs`, or the in-repo canonicaliser from T-155 if the crate is unsuitable (decision recorded in the Report); a test fixes known vectors from RFC 8785.
> **Module gating** (MOD rules): being toggleable, the module's routes return 404 `module_disabled` when off (T-017 gate), and the module matrix job (TST-07) gains `sync` off.

## Scope

**In:**
- Crate skeleton, descriptor (`kind toggleable`, default on, `requires: ["tasks"]`, optional `knowledge`), migration, grants, `domain/hash.rs` (`payload_hash`), `infra/ledger.rs` (`find`, `insert_or_replay`), wiring: Register the crate in the workspace `Cargo.toml`, as a dependency of `kinevo-app`, and in the composition root list (`kinevo-app/src/modules.rs`), in dependency order; the architecture test of T-042 must stay green.

**Out:**
- Reconcile logic: T-252. Operation executors: T-251.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-sync/**` | create | skeleton |
| `crates/kinevo-mod-sync/migrations/20270301000000_sync_sync.sql` | create |  |
| `crates/kinevo-mod-sync/tests/{isolation,descriptor,hash,ledger}.rs` | create | AC-1…AC-8 |
| `Cargo.toml, crates/kinevo-app/**` | modify | wiring |

## Interfaces

```rust
pub fn payload_hash(op: &OpEnvelope) -> [u8; 32];
pub enum LedgerLookup { Miss, Replay(StoredResult), Reused }
pub async fn lookup(tx: &mut Tx, op_id: OperationId, hash: [u8; 32]) -> Result<LedgerLookup, AppError>;
```

## Business rules

1. Absent optionals are omitted from the hash input; present `null` is distinct from absent.
2. The ledger is per account: the same `operation_id` in two accounts is two rows.
3. A unique violation on insert is never an error; it triggers a re-read.

## Security

- `run_isolation` covers `offline_operations` (SEC-AC-26).
- Stored `result` contains the entity representation the caller could read anyway.

## Implementation notes

- The hash covers `workspace_id` so an op replayed into another workspace is `operation_id_reused`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a migrated database **When** compared with the DDL **Then** equal | `isolation::dom_offline_operations_matches_ddl` |
| AC-2 | **Given** two accounts **When** `run_isolation` runs **Then** clean; the same `operation_id` works in both | `isolation::sec_ac_26_ledger_is_per_account` |
| AC-3 | **Given** RFC 8785 test vectors **When** hashed **Then** the canonical bytes match | `hash::canonical_vectors` |
| AC-4 | **Given** absent vs null optional fields **When** hashed **Then** different hashes | `hash::absent_differs_from_null` |
| AC-5 | **Given** a stored result and the same hash **When** looked up **Then** `Replay` | `ledger::syn_fr_01_replay_equal` |
| AC-6 | **Given** the same id and a different payload **When** looked up **Then** `Reused` | `ledger::sec_ac_25_operation_id_reused` |
| AC-7 | **Given** 20 parallel inserts of one id **When** run **Then** one row and 19 replays | `ledger::syn_fr_02_parallel_insert_single_row` |
| AC-8 | **Given** the descriptor **When** validated **Then** it is toggleable with the right dependencies | `descriptor::mod_08_sync_descriptor_valid` |

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
