---
id: T-019
title: Add the idempotency layer, If-Match handling and the cookie-endpoint origin guard
milestone: M1
kind: feature
status: todo
depends_on: [T-017, T-015, T-008]
requirements: [API-11, API-60, API-61, API-62, API-70, API-71, API-72, SEC-09, ERR-06, TST-20]
branch: m1-t019-http-idempotency-csrf
budget_lines: 380
assignee: ""
---

# T-019: Add the idempotency layer, If-Match handling and the cookie-endpoint origin guard

## Goal

Mutating routes that opt in get exactly-once semantics from the `Idempotency-Key` header, versioned resources get a uniform `If-Match` extractor, and cookie-based endpoints get the CSRF/origin defence of SEC-09. `kinevo-http` stays free of database types by using a port.

## Context

> **API-70/71/72** (`07` §8): Clients send `Idempotency-Key: <uuid>` on `POST` that creates or commands, and on `DELETE`; the server stores `(account, key)` with a hash of method+path+body and the response for `api.idempotency.ttl`. Same key + same request → replay with `Idempotency-Replayed: true` (also deterministic errors; not 5xx or 429). Same key + different request → 422 `idempotency_key_reuse`. Same key in flight → 409 `idempotency_in_progress` (`Retry-After: 1`). Keys are per account.
> **Store semantics** (T-015): `begin` reserves the key in its own short transaction (committed **before** the handler runs), the handler runs in its own transaction(s), and `finish` stores the response afterwards. `store_body = false` on secret-bearing routes (`/auth/*`, `/setup/*`, `/account/totp/*`). Bodies above 262144 bytes are not stored.
> **ERR-06**: Idempotent deterministic errors may be replayed; 5xx and 429 are never stored.
> **API-60**: Versioned resources expose `version`. `PATCH`/`PUT`/`DELETE` on them require `If-Match: "<version>"`. Missing → 428 `precondition_required` (`details.header = "If-Match"`). Mismatch → 409 `version_conflict` with `details.current_version` and `details.current`. **API-61**: state-dependent commands take `base_version` in the body and behave the same. **API-62**: no last-write-wins.
> **API-11 / SEC-09**: `POST /auth/refresh` and `/auth/logout` additionally require header `X-Kinevo-Client: web|android` and a matching `Origin` (CSRF defence in depth). The cookie is `SameSite=Strict`; CORS allows no origin except the Android shell origin (D-37); `Access-Control-Allow-Credentials` is never sent. Failure → 403 `csrf_check_failed`.
> **Android origin** (`11-operations.md` §2): `KINEVO_ANDROID_ORIGIN` (default `true`) allows CORS for `https://app.kinevo.localhost` (D-37); the Android client sends `X-Kinevo-Client: android` and no cookie.
> **API-73**: Offline operations use `operation_id` in the sync envelope instead of `Idempotency-Key`; both may not be mixed on one request (enforced later by the sync module; this layer rejects the combination on routes flagged `sync`: not in M1).
> **Policy** `api.idempotency.ttl` (1 h–7 d, default 86400).

## Scope

**In:**
- Port `IdempotencyPort` in `kinevo-ports::platform` (`#[async_trait]`): `begin(account: AccountId, key: &str, method: &str, path: &str, request_hash: [u8;32], ttl_seconds: i64) -> Result<IdemBegin, AppError>`, `finish(account, key, status: u16, body: Option<Vec<u8>>) -> Result<(), AppError>`, `release(account, key)`; `IdemBegin::{Fresh, Replay{status, body}, InProgress, Mismatch}`; fake `FakeIdempotency` in `kinevo_ports::fakes`. The real adapter over `kinevo_db::idempotency` is wired in T-039.
- `kinevo_http::idempotency`: layer applied to routes with `IdemMode::Optional` and an authenticated `AuthCtx`: parses the header (`IdemKey` rules: 8–128 chars `[A-Za-z0-9_-]`; invalid → 422 `validation_failed` with a field error at path `Idempotency-Key`), buffers the body (bounded by the global limit), computes `request_hash = SHA-256(method \0 route_path_with_params \0 raw_body)`, calls `begin`, then: `Fresh` → run handler, `finish` (status < 500 and ≠ 429; else `release`), `Replay` → rebuild the stored response with `Idempotency-Replayed: true`, `InProgress` → 409 `idempotency_in_progress` + `Retry-After: 1`, `Mismatch` → 422 `idempotency_key_reuse`. A request without the header is processed normally (the header is advisory for clients in 1.0; documented in the OpenAPI description by T-041).
- Secret routes: `RouteSpec` gains `store_response_body: bool` (default true); `/auth/*`, `/setup/*`, `/account/totp/*` set false. Replay of such a route returns the stored status with an empty body and header `Idempotency-Replayed: true`.
- Replay stores/restores only status, `content-type` and body (no other headers, never `Set-Cookie`).
- `kinevo_http::concurrency`: `IfMatch` extractor → `Version(i64)`; header must be a quoted positive integer (`"7"`); missing → 428 `precondition_required`; malformed → 422 `validation_failed` (field error path `If-Match`, code `int_invalid`); `fn version_conflict<T: Serialize>(current_version: i64, current: &T) -> AppError` builds the 409 with `details.current_version` and `details.current` (the typed `Details` of T-003 gains a `Json(serde_json::Value)` variant **restricted to this one constructor**, because `current` is the resource representation; add the variant as `DetailValue::Resource(serde_json::Value)`, constructible only through `version_conflict`).
- `kinevo_http::origin`: `OriginGuard` layer for routes flagged `cookie_endpoint: bool` in `RouteSpec`: requires `X-Kinevo-Client` ∈ {`web`,`android`}; for `web` requires `Origin` equal to the configured public origin (scheme + host + port); for `android` requires nothing about `Origin` but forbids a cookie header. Failure → 403 `csrf_check_failed` and an audit hook call `on_csrf_failure` (the identity module records audit/log). CORS: a small layer answering preflight and adding `Access-Control-Allow-Origin` only for `https://app.kinevo.localhost` when `android_origin` is enabled; never `Allow-Credentials`.

**Out:**
- The real `IdempotencyPort` adapter and the `Tx` plumbing: T-039.
- The sync module's `operation_id` mixing check (M5).
- Cookie issuing/clearing: T-024.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/platform.rs` | modify | `IdempotencyPort`, `IdemBegin` |
| `crates/kinevo-ports/src/fakes.rs` | modify | `FakeIdempotency` |
| `crates/kinevo-kernel/src/errors/mod.rs` | modify | `DetailValue::Resource` variant (+ constructor restriction test) |
| `crates/kinevo-http/src/idempotency.rs` | create | layer |
| `crates/kinevo-http/src/concurrency.rs` | create | `IfMatch`, `version_conflict` |
| `crates/kinevo-http/src/origin.rs` | create | `OriginGuard`, CORS layer |
| `crates/kinevo-http/src/route.rs` | modify | new `RouteSpec` flags, layer installation order |
| `crates/kinevo-http/tests/idempotency.rs`, `tests/origin.rs`, `tests/if_match.rs` | create | AC tests |

## Interfaces

```rust
pub struct Version(pub i64);   // from If-Match: "7"
pub fn version_conflict<T: Serialize>(current_version: i64, current: &T) -> AppError;   // 409 version_conflict
pub struct OriginConfig { pub public_origin: Option<String>, pub android_origin_enabled: bool }
// RouteSpec additions: store_response_body: bool, cookie_endpoint: bool
```

Layer order inside `RouteTable::into_router` (outermost first): request id → panic → trace → headers → body limit → content-type → CORS → origin guard → rate limit → gate (T-017) → idempotency → handler. (T-018's layer sits where listed; adjust T-016/T-017 installation code accordingly.)

## Business rules

1. A replayed response is byte-identical in status and body to the original, plus `Idempotency-Replayed: true`.
2. A 500, 503 or 429 response is never stored; a retry with the same key runs the handler again.
3. Same key + same body but different path → `idempotency_key_reuse`.
4. Two concurrent requests with the same new key: exactly one runs the handler; the other gets 409 `idempotency_in_progress`.
5. A missing `If-Match` is 428 even when the resource does not exist (the check precedes lookup), except on routes that create.
6. `Origin` comparison is exact on scheme, host and port; `null`, a missing header or a different origin on a `web` request is `csrf_check_failed`.
7. `GET`, `HEAD`, `OPTIONS` are never subject to idempotency handling.

## Security

- Stored bodies may carry S2 data: stored only for the TTL, per account (RLS in T-015); `store_response_body = false` for secret-bearing routes, and `Set-Cookie` is never stored or replayed.
- CORS is closed by default; no wildcard; credentials header never sent (SEC-09).
- Failure responses of the guard reveal nothing about which check failed.

## Implementation notes

- Buffer the request body once (`axum::body::to_bytes` with the configured limit) so the hash and the handler see the same bytes.
- `IdempotencyPort::begin` runs in its own transaction inside the adapter; this crate never assumes a transaction.
- Keep the layer small: it is a state machine over `IdemBegin`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a `POST` with a new key **When** sent twice with the same body **Then** the handler ran once; the second response equals the first plus `Idempotency-Replayed: true` | `idempotency::api_71_replay_identical_response` |
| AC-2 | **Given** a stored key **When** reused with a different body **Then** 422 `idempotency_key_reuse` | `idempotency::api_71_reuse_with_other_body` |
| AC-3 | **Given** a slow handler and a second request with the same key **When** sent concurrently **Then** the second gets 409 `idempotency_in_progress` with `Retry-After: 1` and the handler ran once | `idempotency::tst_20_concurrent_same_key` |
| AC-4 | **Given** a handler returning 503 then 201 **When** the same key is sent twice **Then** the handler ran twice and only the 201 is stored | `idempotency::err_06_5xx_not_stored` |
| AC-5 | **Given** the same key from two accounts **When** each posts **Then** both run (per-account keys, via the fake port) | `idempotency::api_72_per_account_keys` |
| AC-6 | **Given** a route with `store_response_body = false` **When** replayed **Then** status only, empty body, no `Set-Cookie` | `idempotency::secret_routes_replay_status_only` |
| AC-7 | **Given** keys `short`, 129 characters, `a b c d e f g h` **When** sent **Then** 422 with a field error at `Idempotency-Key` | `idempotency::key_header_validation` |
| AC-8 | **Given** `PATCH` with no `If-Match`, `If-Match: 7` (unquoted), `If-Match: "7"` **When** extracted **Then** 428, 422, `Version(7)` | `if_match::api_60_extractor_cases` |
| AC-9 | **Given** `version_conflict(8, &resource)` **When** rendered **Then** 409 with `details.current_version = 8` and `details.current` equal to the resource JSON | `if_match::api_60_conflict_body` |
| AC-10 | **Given** a cookie endpoint **When** called with missing `X-Kinevo-Client`, wrong value, wrong `Origin`, `Origin: null`, and correct headers **Then** 403 `csrf_check_failed` ×4 and success | `origin::sec_09_csrf_matrix` |
| AC-11 | **Given** CORS enabled for the Android origin **When** a preflight from that origin and from `https://evil.example` arrive **Then** only the first is allowed and `Access-Control-Allow-Credentials` is absent in both | `origin::d_37_cors_android_only` |
| AC-12 | **Given** `DetailValue::Resource` **When** constructing an `AppError` outside `version_conflict` **Then** it does not compile (constructor private; verified with a `compile_fail` doctest) | `kinevo_kernel::errors (doctest: compile_fail)` |

## Tests

```
cargo nextest run -p kinevo-kernel -p kinevo-ports -p kinevo-http
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes.
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
