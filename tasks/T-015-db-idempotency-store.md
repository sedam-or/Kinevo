---
id: T-015
title: Add the idempotency store (begin, replay, conflict, finish)
milestone: M1
kind: feature
status: review
depends_on: [T-012, T-005]
requirements: [API-70, API-71, API-72, ADM-FR-08, D-34, D-80, D-81, D-82, D-83, TST-20]
branch: m1-t015-db-idempotency-store
budget_lines: 280
assignee: "Codex"
---

# T-015: Add the idempotency store (begin, replay, conflict, finish)

## Goal

`kinevo-db` offers the storage half of idempotent requests: reserve a key, replay a stored response, detect reuse with a different request, and detect an in-flight duplicate. The HTTP middleware that uses it is T-019.

## Context

> **API-70** (`07-api-conventions.md` §8, D-34): Clients send `Idempotency-Key: <uuid>` on every `POST` that creates or commands, and on `DELETE`. The server stores `(account, key)` with a hash of method+path+body and the response for `api.idempotency.ttl`.
> **API-71**: Same key + same request → the stored response is replayed with header `Idempotency-Replayed: true` (also for error responses that are deterministic; **not** for 5xx or 429, which are never stored). Same key + different request → 422 `idempotency_key_reuse`. Same key while the first is in flight → 409 `idempotency_in_progress` (client retries after `Retry-After: 1`).
> **API-72**: Keys are bound to the account.
> **Schema** (`06` §3): `idempotency_keys(account_id, key text 8–128, method, path, request_hash bytea, status_code smallint null while in flight, response_content_type text, response_body bytea, created_at, expires_at, pk(account_id, key))`; standard `tenant_isolation` RLS; `kinevo_app` has SELECT, INSERT, UPDATE and no DELETE (T-012). Expired rows are pruned by `platform_prune_idempotency` (ADM-FR-08).
> **Validation** (`validation.md` §3): `idempotency_key` is `key`-like, 8–128 chars `[A-Za-z0-9_-]`.
> **Policy** `api.idempotency.ttl` (duration 1 h–7 d, default 86400), supplied by the caller.
> **Hash** (`contracts.md` §3): when bytes are hashed, SHA-256; for request bodies the middleware hashes the **raw body bytes** after the size limit, not re-serialised JSON, so `request_hash = SHA-256(method \0 path_and_query_template \0 body)` (document this on `request_hash`).

## Scope

**In:**
- `kinevo_db::idempotency`:
  - `IdemKey` newtype re-exported from `kinevo-kernel::validate` (`parse`, per the validation rule above; D-83).
  - `begin(tx: &mut Tx, key: &IdemKey, method: &str, path: &str, request_hash: [u8;32], now: Instant, ttl_seconds: i64) -> Result<Begin, AppError>` where `Begin = Fresh | Replay(StoredResponse) | InProgress | Mismatch`. It runs `INSERT … ON CONFLICT (account_id, key) DO NOTHING`; when the row already exists and `expires_at > now` it classifies the existing row; an expired row is replaced (UPDATE to a fresh in-flight reservation).
  - `finish(tx: &mut Tx, key: &IdemKey, status: u16, body: &[u8]) -> Result<(), AppError>` storing the response (status < 500 and ≠ 429 only; otherwise the reservation is released with `release`).
  - `release(tx, key)` — deletes nothing (no DELETE grant): it sets `expires_at = created_at` so the key is immediately reusable; documented.
  - `StoredResponse { status: u16, content_type: Option<String>, body: Vec<u8> }`. `finish` keeps its status/body compatibility entry point; T-019 adds `finish_with_content_type` so replay can preserve the media type (D-82). A response body larger than `MAX_STORED_BODY_BYTES` (262144, a technical constant) is not stored: `finish` releases the key instead, so a retry runs the request again.

**Out:**
- The middleware and header handling: T-019.
- The prune job: T-038.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-db/src/idempotency.rs` | create | |
| `crates/kinevo-db/src/lib.rs` | modify | `pub mod idempotency;` |
| `crates/kinevo-db/tests/idempotency.rs` | create | |

## Interfaces

```rust
pub struct IdemKey(String);  impl IdemKey { pub fn parse(raw: &str) -> Result<Self, FieldCode>; }
pub enum Begin { Fresh, Replay(StoredResponse), InProgress, Mismatch }
pub async fn begin(tx: &mut Tx, key: &IdemKey, method: &str, path: &str, request_hash: [u8; 32], now: Instant, ttl_seconds: i64) -> Result<Begin, AppError>;
pub async fn finish(tx: &mut Tx, key: &IdemKey, status: u16, body: &[u8]) -> Result<(), AppError>;
pub async fn release(tx: &mut Tx, key: &IdemKey) -> Result<(), AppError>;
```

## Business rules

1. The reservation (`begin` → `Fresh`) must be committed **before** the handler runs, in its own short `Tx`; the handler runs in a second `Tx`; `finish` runs in a third (or in the handler's own transaction when the middleware passes it). Document this on `begin` — otherwise a rollback would erase the reservation and break the in-flight guarantee. (T-019 owns the orchestration.)
2. Same key + same `(method, path, request_hash)` + response stored → `Replay`; same key, response not yet stored → `InProgress`; same key, different hash/method/path → `Mismatch`.
3. A key of another account never matches (RLS and the composite primary key).
4. 5xx and 429 responses are never stored.
5. After `expires_at` the key is treated as new.

## Security

- Stored bodies may contain S2 data (task titles). They are kept only for the TTL, are covered by RLS, are never logged, and are excluded from export (`export_v1` contains no idempotency data).
- Bodies of responses that carry secrets (login tokens, TOTP enrolment, setup finish) must not be stored: `finish` takes a `store_body: bool` flag set by the route descriptor (default false for `/auth/*`, `/setup/*`, `/account/totp/*`); when false only `status_code` is stored and replay returns `Replay` with an empty body and `status` — T-019 documents that those routes use idempotency for status-only dedup. Add the parameter to `finish` and test it.

## Implementation notes

- Compare `request_hash` with `ct_eq` (T-004) to avoid a timing side channel on stored hashes (low risk, cheap).
- `method` is stored upper-case; `path` is the concrete URI path with parameter values, but not the query string for creates (D-81; `MatchedPath` identifies route metadata only).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a new key **When** `begin` runs **Then** `Fresh`; after `finish(201, body)` a second `begin` with the same request returns `Replay` with the same status and body | `idempotency::api_71_replay_same_request` |
| AC-2 | **Given** a reserved key without a stored response **When** `begin` runs again **Then** `InProgress` | `idempotency::api_71_in_flight_duplicate` |
| AC-3 | **Given** a stored key **When** `begin` runs with a different body hash, method or path **Then** `Mismatch` | `idempotency::api_71_reuse_with_different_request` |
| AC-4 | **Given** the same key string in accounts A and B **When** both begin **Then** both are `Fresh` and neither sees the other's response | `idempotency::api_72_keys_are_per_account` |
| AC-5 | **Given** `finish` with status 500 or 429 **When** the key is begun again **Then** `Fresh` (nothing stored) | `idempotency::api_71_5xx_and_429_not_stored` |
| AC-6 | **Given** a stored key and the clock past `expires_at` **When** `begin` runs **Then** `Fresh` | `idempotency::ttl_expiry_allows_reuse` |
| AC-7 | **Given** `store_body = false` **When** replayed **Then** the status is returned with an empty body and the database column is null | `idempotency::secret_routes_store_status_only` |
| AC-8 | **Given** 8 concurrent `begin` calls with the same new key **When** they finish **Then** exactly one `Fresh`, the others `InProgress` | `idempotency::tst_20_concurrent_begin_one_winner` |
| AC-9 | **Given** keys `abc`, 129 characters, `has space` **When** parsed **Then** all rejected | `idempotency::tests::key_validation` |

## Tests

```
cargo nextest run -p kinevo-db --features testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Summary

`kinevo-db` now reserves account-scoped idempotency keys, classifies replays, in-flight requests and mismatched requests, and stores deterministic responses. It releases reservations for 5xx, 429 and oversized bodies, and supports status-only replay when the route must not retain a response body.

### Acceptance results

| Criterion | Test | Result |
|---|---|---|
| AC-1 | `idempotency::api_71_replay_same_request` | pass |
| AC-2 | `idempotency::api_71_in_flight_duplicate` | pass |
| AC-3 | `idempotency::api_71_reuse_with_different_request` | pass |
| AC-4 | `idempotency::api_72_keys_are_per_account` | pass |
| AC-5 | `idempotency::api_71_5xx_and_429_not_stored` | pass |
| AC-6 | `idempotency::ttl_expiry_allows_reuse` | pass |
| AC-7 | `idempotency::secret_routes_store_status_only` | pass |
| AC-8 | `idempotency::tst_20_concurrent_begin_one_winner` | pass |
| AC-9 | `idempotency::tests::key_validation` | pass |
| Scope: oversized response release | `idempotency::response_body_limit_releases_key` | pass |

### Commands run

- `cargo nextest run -p kinevo-db --features testing --test idempotency`: initial expected red exit 101 (`kinevo_db::idempotency` did not yet exist); intermediate test-fixture compile errors E0515/E0507 were corrected; final exit 0, 10 passed, 0 skipped.
- `cargo nextest run -p kinevo-db --features testing`: exit 0, 66 passed, 0 skipped (before adding the oversized-body test; the final workspace gate below includes it).
- `NEXTEST_TEST_THREADS=4 just check`: first exit 101 from Clippy (`clippy::panic` and `clippy::assert_is_empty` in the new tests); corrected. Final exit 0: 173 passed, 0 skipped; fmt, Clippy and cargo-deny completed; spec check 0 findings/60 files, contrast 0 failures, task index 0 problems/132 tasks. Existing warnings remain for the `rand::thread_rng` Clippy configuration entry, duplicate `base64` versions and unused cargo-deny license allowances.
- `cargo fmt --all`: exit 0. The initial `cargo fmt --all --check` exited 1 on formatting differences; corrected before the final gate.
- `python3 scripts/tasks_index.py`: exit 0; regenerated the index after adding D-80 and D-81.
- `python3 scripts/tasks_index.py --check`: exit 0, 0 problems in 132 tasks.
- `python3 scripts/spec_tool.py check`: exit 0, 0 findings in 60 spec files.
- `python3 scripts/contrast.py`: exit 0, 0 failures.
- `git diff --check`: exit 0.

### Files changed

Line counts are recorded from the implementation diff; generated `tasks/index.md` is excluded from the task line budget under D-55.

| Path | Change |
|---|---|
| `crates/kinevo-db/src/idempotency.rs` | new store implementation |
| `crates/kinevo-db/src/lib.rs` | export `idempotency` module |
| `crates/kinevo-db/tests/idempotency.rs` | 10 PostgreSQL acceptance/edge-case tests |
| `spec/decisions.md` | add D-80 and D-81 |
| `spec/index.md` | update decision range |
| `tasks/T-015-db-idempotency-store.md` | record decisions, report, status and questions |
| `tasks/index.md` | regenerated status/decision links |

### Deviations

- The `finish` signature includes `store_body: bool` because the task's Security section and AC-7 require it, although the earlier Scope/Interfaces signatures omit the parameter.
- `release` clears `status_code` and `response_body` while setting `expires_at = created_at`, preventing response bytes from remaining on a released reservation.
- The change is 833 non-generated changed lines against the task's 280-line budget because the nine named acceptance tests plus the oversized-body case require isolated PostgreSQL coverage, and the run requires decision records and a complete task Report. D-55 makes this a reportable deviation, not a blocker; generated `tasks/index.md` adds 6 changed lines and is excluded.

### Decisions made by implementer

| ID | Choice | Alternative and reason | Files | Reversal |
|---|---|---|---|---|
| D-80 | Use `validation.md`'s key-like contract: trim, lowercase, and accept 8–128 ASCII letters, digits, `_` or `-`. | UUID-only parsing conflicts with the task's validator and field-specific rule; the generic `<uuid>` notation is treated as the wire example. Pending owner review. | `spec/decisions.md`, `spec/index.md`, this task, `tasks/index.md`, `idempotency.rs` | Supersede D-80 with an owner decision, then update the parser and tests. |
| D-81 | Store `path` verbatim; T-019 supplies the concrete URI path with parameter values and without query strings for creates. `MatchedPath` is used only for route metadata. | Rewriting paths in `kinevo-db` would duplicate HTTP routing; including query values here conflicts with the task implementation note. Pending owner review. | `spec/decisions.md`, `spec/index.md`, this task, `tasks/index.md`, `idempotency.rs` | Supersede D-81 with an owner decision and update T-019's path construction. |
| D-82 | Persist and replay response content type with eligible response bodies; secret routes keep status only. | Inferring a media type or omitting it from replay would change the response contract. Pending owner review. | T-019 extends the store with a nullable response-content-type column and adapter contract. | Supersede D-82 with an owner decision, then update the migration and port. |
| D-83 | Place the normalized `IdemKey` validator in the kernel and re-export it from the DB module so HTTP stays database-independent. | Duplicating normalization risks drift; adding an HTTP-to-DB dependency violates CA layering. Pending owner review. | `crates/kinevo-kernel/src/validate/key.rs`, `crates/kinevo-db/src/idempotency.rs` | Supersede D-83 with an owner decision and move the shared type accordingly. |

### Spec issues noticed

- API-70's `<uuid>` notation and `validation.md`'s `idempotency_key` rule do not state the same accepted format; D-80 records the chosen interpretation pending owner review.
- The Context hash notation says `path_and_query_template`, while the Implementation notes exclude the query string for creates; D-81 records the storage contract pending owner review.
- The Scope/Interfaces signature omits `store_body`, while Security and AC-7 require it; implemented the more specific security behavior.

### Not done / not verified

- HTTP header parsing and middleware orchestration are T-019; platform pruning is T-038. This task only implements the storage API.
- No environment-limited checks; Docker-backed PostgreSQL tests ran successfully.

### Follow-up suggestions

- T-019 should set `store_body = false` for the secret-bearing route families listed in this task and finalize the caller's canonical path string.

## Questions

- Q-1 (implementer, 2026-10-28): API-70 uses `<uuid>`, while the field-specific validation rule and task contract use key-like values. Resolved by implementer as D-80; pending owner review.
- Q-2 (implementer, 2026-10-28): the hash notation mentions `path_and_query_template`, but the implementation note excludes query strings for creates. Resolved by implementer as D-81; pending owner review.
- Q-3 (implementer, 2026-10-28): Scope/Interfaces omit `store_body`, while Security/AC-7 require it. Resolved by implementer by following Security and AC-7; pending architect review.
