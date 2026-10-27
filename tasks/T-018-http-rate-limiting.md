---
id: T-018
title: Add per-route rate limiting with policy-driven quotas and RateLimit headers
milestone: M1
kind: feature
status: in_progress
depends_on: [T-017, T-006]
requirements: [API-50, API-51, SEC-11, POL-02]
branch: m1-t018-http-rate-limiting
budget_lines: 330
assignee: "OpenAI Codex"
---

# T-018: Add per-route rate limiting with policy-driven quotas and RateLimit headers

## Goal

Routes declare a rate class; a tower layer enforces the matching `ratelimit.*` policy per client address (unauthenticated) or per account (authenticated), answers 429 `rate_limited` with `Retry-After`, and adds `RateLimit-*` headers to responses of limited routes.

## Context

> **API-50**: Limits are policies (`ratelimit.*`). Exceeding → 429 `rate_limited` with `Retry-After` (seconds) and `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset` headers on **all** responses of limited routes.
> **API-51**: Keys: unauthenticated → client address; authenticated → account id. Behind a trusted reverse proxy, the client address is read from `X-Forwarded-For` only if the connecting peer is in `KINEVO_TRUSTED_PROXIES` (CIDR list).
> **SEC-11**: Rate limiting and lockout per `ratelimit.*` and `auth.lockout.*`, enforced in a tower layer before handlers; limiter state is in-process (single-node assumption, documented).
> **Policies** (`04-policies.md` §3.3, scope instance): `ratelimit.auth_per_minute` int 1–120 default 5 (per client address); `ratelimit.api_per_minute` 10–6000 default 120 (per account); `ratelimit.ai_per_minute` 1–120 default 10; `ratelimit.upload_per_minute` 1–600 default 20; `ratelimit.sync_per_minute` 10–6000 default 120; `ratelimit.export_per_minute` 1–120 default 10. The typed `PolicyKey<i64>` constants exist from T-006.
> **Cache** (`containers-and-caching.md` §2): rate limiter key per `ratelimit.*`, counters, window expiry, max 100 000 entries; restart resets counters (accepted).
> **Error**: `rate_limited` 429 retry `backoff`, `details.retry_after_seconds` (T-003).
> **SEC-AC-03** (`identity.md` §8): credential stuffing, 100 logins/min from one address → `rate_limited`.
> **Dependency**: authenticated class `Api` needs the account id, so this layer runs after the gate (T-017) for authenticated routes and before it for `Public` routes of class `Auth` (keyed by address).

## Scope

**In:**
- `kinevo_http::ratelimit`: `RateLimiter` holding one `governor` keyed limiter per `(RateClass, quota)` in a bounded `moka` map (max 100 000 keys, TTL = 2 × window) so memory is bounded; quotas come from `PolicyReader` (T-008) via `ResolvedPolicies`, refreshed at most every 30 s (same TTL as the policy cache) and rebuilt when a value changes.
- Algorithm: fixed one-minute window counter per key (simpler to explain in headers than GCRA): `limit`, `remaining`, `reset_seconds`. Implement with a small struct in this crate (no `governor` dependency unless the implementer shows it fits the header semantics; if `governor` is used, record why in the Report). The injected `Clock` supplies time.
- `ClientAddr` resolution: peer address from `ConnectInfo`, trusted-proxy CIDRs (`TrustedProxies::parse(&str)`), rightmost-untrusted element of `X-Forwarded-For`; invalid header → peer address. IPv6 keys are truncated to /64 for limiting.
- The layer reads `RouteSpec.rate`; class `None` skips. Header names: `RateLimit-Limit`, `RateLimit-Remaining`, `RateLimit-Reset`. On 429 also `Retry-After`.
- Wiring: `RouteTable::into_router` installs the layer; unauthenticated `RateClass::Auth` and `Public` routes are keyed by address, others by `AuthCtx.account_id`.

**Out:**
- Per-account failed-login lockout (T-023) and setup-token attempt counting (T-036).
- Distributed/DB-backed limiter (explicitly deferred, SEC-11).
- `/metrics` counters (later).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-http/src/ratelimit.rs` | create | limiter, layer, `ClientAddr`, `TrustedProxies` |
| `crates/kinevo-http/src/route.rs` | modify | install the layer |
| `crates/kinevo-http/tests/ratelimit.rs` | create | AC tests with `FixedClock` |
| `crates/kinevo-http/Cargo.toml` | modify | `ipnet` (CIDR parsing) |
| `spec/standards/open-source-stack.md` | modify | `ipnet` row |

## Interfaces

```rust
pub struct TrustedProxies(Vec<IpNet>);  impl TrustedProxies { pub fn parse(csv: &str) -> Result<Self, FieldCode>; pub fn client_addr(&self, peer: IpAddr, xff: Option<&str>) -> IpAddr; }
pub enum RateClass { None, Auth, Api, Ai, Upload, Sync, Export }   // defined in T-016; the policy key mapping lives here
pub fn policy_key(class: RateClass) -> Option<&'static PolicyKey<i64>>;
pub struct Decision { pub allowed: bool, pub limit: u32, pub remaining: u32, pub reset_seconds: u32 }
```

## Business rules

1. The window is one minute from the first request of the key; `Reset` is the seconds until the window ends; `Remaining` never goes below 0.
2. The 6th request of the minute from one address on an `Auth` route with the default (5) is rejected; the next minute starts fresh.
3. Authenticated routes are limited per account, not per address, so two accounts behind one NAT do not share a budget.
4. `X-Forwarded-For` is ignored unless the peer is in the trusted list; with the list `10.0.0.0/8` and header `1.2.3.4, 10.0.0.5` from peer `10.0.0.9`, the client is `1.2.3.4`.
5. A changed policy value takes effect within 30 s without restart.
6. Rejected requests do not reach handlers and are not counted twice.

## Security

- Keys are hashed (SHA-256, truncated) before being stored in the map, so addresses are not kept in cleartext in memory dumps and never logged (SEC-04 allows only truncated /24 or /48 in security events).
- The 429 body is the standard problem body; it reveals no counters beyond `retry_after_seconds`.

## Implementation notes

- Tests drive time with the `FixedClock` (no sleeps, CQ-18).
- `moka::future::Cache` with `time_to_live` only needs expiry hints; the logical window is computed from the injected clock stored in the entry.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** limit 5 and an `Auth` route **When** one address sends 6 requests in one minute **Then** requests 1–5 pass with decreasing `RateLimit-Remaining`, the 6th is 429 with `Retry-After` and `details.retry_after_seconds` | `ratelimit::api_50_sixth_request_is_limited` |
| AC-2 | **Given** the clock advanced past the window **When** the same address requests **Then** it passes with `Remaining = limit − 1` | `ratelimit::window_resets_with_injected_clock` |
| AC-3 | **Given** two accounts from the same address on an `Api` route **When** each sends `limit` requests **Then** neither is limited by the other | `ratelimit::api_51_authenticated_keyed_by_account` |
| AC-4 | **Given** trusted proxy `10.0.0.0/8` **When** the example header arrives from peer `10.0.0.9` and from untrusted peer `8.8.8.8` **Then** the client addresses are `1.2.3.4` and `8.8.8.8` | `ratelimit::tests::api_51_trusted_proxy_resolution` |
| AC-5 | **Given** the policy value changes from 5 to 2 **When** 31 s pass (fake clock) **Then** the third request in a window is limited | `ratelimit::policy_change_applies_within_30s` |
| AC-6 | **Given** a route with class `None` **When** requested many times **Then** no `RateLimit-*` headers and no limiting | `ratelimit::none_class_skips` |
| AC-7 | **Given** 100 login-like requests/min from one address against `Auth` **When** sent **Then** all beyond the limit are 429 and handlers ran at most `limit` times | `ratelimit::sec_ac_03_credential_stuffing_is_throttled` |
| AC-8 | **Given** more distinct keys than the map bound **When** inserted **Then** memory stays bounded (entry count ≤ max) and requests still work | `ratelimit::cac_1_bounded_keys` |

## Tests

```
cargo nextest run -p kinevo-http
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
