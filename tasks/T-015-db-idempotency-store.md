---
id: T-015
title: Add the idempotency store (begin, replay, conflict, finish)
milestone: M1
kind: feature
status: todo
depends_on: [T-012, T-005]
requirements: [API-70, API-71, API-72, ADM-FR-08, D-34, TST-20]
branch: m1-t015-db-idempotency-store
budget_lines: 280
assignee: ""
---

# T-015: Add the idempotency store (begin, replay, conflict, finish)

## Goal

`kinevo-db` offers the storage half of idempotent requests: reserve a key, replay a stored response, detect reuse with a different request, and detect an in-flight duplicate. The HTTP middleware that uses it is T-019.

## Context

> **API-70** (`07-api-conventions.md` §8, D-34): Clients send `Idempotency-Key: <uuid>` on every `POST` that creates or commands, and on `DELETE`. The server stores `(account, key)` with a hash of method+path+body and the response for `api.idempotency.ttl`.
> **API-71**: Same key + same request → the stored response is replayed with header `Idempotency-Replayed: true` (also for error responses that are deterministic; **not** for 5xx or 429, which are never stored). Same key + different request → 422 `idempotency_key_reuse`. Same key while the first is in flight → 409 `idempotency_in_progress` (client retries after `Retry-After: 1`).
> **API-72**: Keys are bound to the account.
> **Schema** (`06` §3): `idempotency_keys(account_id, key text 8–128, method, path, request_hash bytea, status_code smallint null while in flight, response_body bytea, created_at, expires_at, pk(account_id, key))`; standard `tenant_isolation` RLS; `kinevo_app` has SELECT, INSERT, UPDATE and no DELETE (T-012). Expired rows are pruned by `platform_prune_idempotency` (ADM-FR-08).
> **Validation** (`validation.md` §3): `idempotency_key` is `key`-like, 8–128 chars `[A-Za-z0-9_-]`.
> **Policy** `api.idempotency.ttl` (duration 1 h–7 d, default 86400), supplied by the caller.
> **Hash** (`contracts.md` §3): when bytes are hashed, SHA-256; for request bodies the middleware hashes the **raw body bytes** after the size limit, not re-serialised JSON, so `request_hash = SHA-256(method \0 path_and_query_template \0 body)` (document this on `request_hash`).

## Scope

**In:**
- `kinevo_db::idempotency`:
  - `IdemKey` newtype (`parse`, per the validation rule above).
  - `begin(tx: &mut Tx, key: &IdemKey, method: &str, path: &str, request_hash: [u8;32], now: Instant, ttl_seconds: i64) -> Result<Begin, AppError>` where `Begin = Fresh | Replay(StoredResponse) | InProgress | Mismatch`. It runs `INSERT … ON CONFLICT (account_id, key) DO NOTHING`; when the row already exists and `expires_at > now` it classifies the existing row; an expired row is replaced (UPDATE to a fresh in-flight reservation).
  - `finish(tx: &mut Tx, key: &IdemKey, status: u16, body: &[u8]) -> Result<(), AppError>` storing the response (status < 500 and ≠ 429 only; otherwise the reservation is released with `release`).
  - `release(tx, key)` — deletes nothing (no DELETE grant): it sets `expires_at = created_at` so the key is immediately reusable; documented.
  - `StoredResponse { status: u16, body: Vec<u8> }`. A response body larger than `MAX_STORED_BODY_BYTES` (262144, a technical constant) is not stored: `finish` releases the key instead, so a retry runs the request again.

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
- `method` is stored upper-case; `path` is the matched route template plus the concrete path parameters, but not the query string for creates (document the choice; T-019 fixes the exact string).

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

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
