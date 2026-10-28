---
id: T-024
title: Implement POST /auth/refresh, /auth/logout, /auth/logout-all and the refresh cookie
milestone: M1
kind: feature
status: review
depends_on: [T-023]
requirements: [API-11, ID-FR-05, ID-FR-06, SEC-09, SEC-AC-04, SEC-AC-05, D-37]
branch: m1-t024-identity-refresh-logout
budget_lines: 330
assignee: Codex
---

# T-024: Implement POST /auth/refresh, /auth/logout, /auth/logout-all and the refresh cookie

## Goal

Clients can renew a session with the cookie (web) or the body token (Android), sign out of one session, and sign out everywhere. The cookie has the exact attributes of the spec, and cross-site requests are refused.

## Context

> **Endpoints** (`identity.md` §2): `POST /auth/refresh` — cookie (web) or body `{ refresh_token }` (`X-Kinevo-Client: android`) → 200 `{ access_token, expires_in }` + rotated cookie (web) or `+ refresh_token` in the body (android); errors `unauthenticated` 401, `refresh_reuse_detected` 401, `csrf_check_failed` 403. `POST /auth/logout` — cookie (web) or body token (Android) → 204, family revoked, cookie cleared for web, idempotent. `POST /auth/logout-all` — permission `identity.session.manage` → 204, all families revoked.
> **Cookie** (`auth.md` §4): `__Secure-kinevo_rt`: `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age=<remaining absolute lifetime>`; no `Domain`; not `__Host-` because the narrow path is required. Required headers on cookie endpoints: `X-Kinevo-Client: web` (or `android` for the body flow) and `Origin` equal to the public URL.
> **API-11 / SEC-09**: web cookie requests require `X-Kinevo-Client: web` and a matching `Origin`; Android body-token requests require `X-Kinevo-Client: android`, reject cookies, and may omit `Origin` (D-101; T-019 `OriginGuard`).
> **Android flow** (`auth.md` §5, D-37): `POST /auth/refresh` with `X-Kinevo-Client: android` and body `{ "refresh_token": "…" }`; rotation/reuse/grace identical; the response body returns the new `refresh_token`; no `Set-Cookie`.
> **ID-FR-06**: Access tokens are held only in JS memory; a page reload restores the session by calling `/auth/refresh` (E2E in T-056).
> **SEC-AC-04**: refresh token replay from a stolen copy → family revoked, attacker and victim must re-login, audit `auth.refresh_reuse`. **SEC-AC-05**: CSRF against `/auth/refresh` from another origin → 403 `csrf_check_failed`.
> **Services**: `RefreshService` (T-022) implements the rotation; `session_response` (T-023) delivers tokens; revocation helpers exist in `infra::sessions`.
> **Rate limit**: refresh and logout are authenticated by cookie only, so they use class `Auth` (per address); `logout-all` is bearer-authenticated and uses `Api`.

## Scope

**In:**
- Handlers and `RouteSpec`s: `POST /auth/refresh` (`Access::Public`, `RateClass::Auth`, `cookie_endpoint: true`, `store_response_body: false`), `POST /auth/logout` (`Public`, `cookie_endpoint: true`), `POST /auth/logout-all` (`Permission("identity.session.manage")`, `RateClass::Api`).
- Cookie helpers `http::cookie`: `build_refresh_cookie(token, remaining_seconds, secure: bool) -> Cookie`, `clear_refresh_cookie()`, `read_refresh_cookie(&CookieJar)`; `secure` follows the public URL scheme (https → `Secure`; the `__Secure-` prefix requires `Secure`, so in plain-http dev the cookie name falls back to `kinevo_rt` and `SameSite=Strict` stays; document this; tests cover both).
- Logout: revoke the presented token's family with reason `logout`; web reads only its cookie and returns 204 with a cleared cookie even when missing or unknown; Android reads only its body token and returns 204 without a cookie header. Both are idempotent.
- Logout-all: `revoke_all_for_account` with reason `logout` for every family of the caller; the response clears the cookie.
- Response bodies: refresh → `{ access_token, expires_in }` (+ `refresh_token` for android); never the account profile.
- Wiring of the `OriginGuard` and `X-Kinevo-Client` rules from T-019 to these routes.

**Out:**
- Device/session listing and revoke-one: T-026.
- Web Locks cross-tab serialisation: web task (T-048).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/http/refresh.rs` | create | handlers |
| `crates/kinevo-mod-identity/src/http/cookie.rs` | create | cookie helpers |
| `crates/kinevo-mod-identity/src/http/session_response.rs` | modify | uses `cookie.rs` |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | routes |
| `crates/kinevo-mod-identity/tests/refresh_http.rs` | create | HTTP-level tests |
| `crates/kinevo-mod-identity/Cargo.toml` | modify | `axum-extra` (cookie) |

## Interfaces

```rust
pub const REFRESH_COOKIE_SECURE: &str = "__Secure-kinevo_rt"; pub const REFRESH_COOKIE_PLAIN: &str = "kinevo_rt";
pub struct RefreshBody { pub refresh_token: Option<Secret<String>> }   // android only; deny_unknown_fields
```

## Business rules

1. Web refresh reads only the cookie; a body token is ignored for `web`. Android reads only the body; a cookie is ignored for `android`.
2. `Max-Age` = remaining absolute lifetime of the family (not the idle TTL), recomputed on each rotation.
3. A successful refresh never extends `absolute_expires_at`.
4. After reuse detection the response clears the cookie (web).
5. Logout is idempotent: second call → 204.
6. `logout-all` revokes every family of the account including the current one.
7. Cookie attributes are exactly: `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth` and `Max-Age`; no `Domain`.

## Security

- The cookie is the only place the web refresh token lives; it is never echoed in a body for `web`.
- Origin and client-header checks run before any database work (cheap rejection, no oracle).
- Failure bodies are uniform (`unauthenticated`).
- Android: the response includes the token in the JSON body; document that the shell must store it only through `SecureStore` (Android milestone M10).

## Implementation notes

- Reuse `OriginGuard` configured with the public origin from `HttpState`.
- For `SameSite=Strict` use `axum_extra::extract::cookie::SameSite::Strict`.
- `Set-Cookie` must never be stored by the idempotency layer (T-019); these routes have `store_response_body = false`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a login cookie **When** `POST /auth/refresh` with correct headers **Then** 200 `{access_token, expires_in}`, a rotated cookie whose attributes equal §4 exactly, and no `refresh_token` in the body | `refresh_http::id_fr_05_web_refresh_rotates_cookie` |
| AC-2 | **Given** `X-Kinevo-Client: android` and a body token **When** refreshed **Then** the body has a new `refresh_token`, no `Set-Cookie` | `refresh_http::d_37_android_refresh_body_flow` |
| AC-3 | **Given** a request from `https://evil.example` or without `X-Kinevo-Client` **When** `POST /auth/refresh` **Then** 403 `csrf_check_failed` and no database access (spy on the fake db) | `refresh_http::sec_ac_05_csrf_refresh` |
| AC-4 | **Given** a stolen older token **When** presented after the grace window **Then** 401 `refresh_reuse_detected`, the cookie is cleared, and the legitimate holder's next refresh also fails | `refresh_http::sec_ac_04_replay_kills_family` |
| AC-5 | **Given** a family **When** `POST /auth/logout` twice **Then** 204 both times, the family is revoked once, and the cookie is cleared | `refresh_http::logout_is_idempotent` |
| AC-6 | **Given** three sessions **When** `POST /auth/logout-all` with a valid access token **Then** 204 and all three families are revoked; refresh with any of their tokens fails | `refresh_http::logout_all_revokes_every_family` |
| AC-7 | **Given** an https public URL and an http one **When** cookies are built **Then** names and attributes follow Business rules 7 and the dev fallback | `cookie::tests::auth_cookie_attributes` |
| AC-8 | **Given** the absolute lifetime has 1 day left **When** refreshed **Then** `Max-Age` ≈ 86400 and `absolute_expires_at` is unchanged | `refresh_http::absolute_lifetime_not_extended` |
| AC-9 | **Given** an Android body-token session **When** `POST /auth/logout` is called **Then** the family is revoked, 204 is returned, and no cookie is set | `refresh_http::d_37_android_logout_body_flow` |

## Tests

```
cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Result

Implemented the web-cookie and Android body-token refresh/logout flows, cookie lifetime handling, logout-all, and synchronous cleanup for each disposable test database. Status is `review`.

### Acceptance

| AC | Named test | Result |
|---|---|---|
| AC-1 | `id_fr_05_web_refresh_rotates_cookie` | PASS |
| AC-2 | `d_37_android_refresh_body_flow` | PASS |
| AC-3 | `sec_ac_05_csrf_refresh` | PASS |
| AC-4 | `sec_ac_04_replay_kills_family` | PASS |
| AC-5 | `logout_is_idempotent` | PASS |
| AC-6 | `logout_all_revokes_every_family` | PASS |
| AC-7 | `cookie::tests::auth_cookie_attributes` | PASS |
| AC-8 | `absolute_lifetime_not_extended` | PASS |
| AC-9 | `d_37_android_logout_body_flow` | PASS |

### Commands run

- `cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing` with the shared PostgreSQL server, `CARGO_NET_OFFLINE=true`, and `NEXTEST_TEST_THREADS=1`: 50 passed, 1 skipped. This includes all nine T-024 acceptance tests.
- `cargo nextest run -p kinevo-db --features testing --test isolation_harness test_db_is_isolated_per_test`: 1 passed, 5 skipped; the test verified distinct databases and that each explicitly dropped database disappeared before the test continued.
- `just check` with the same environment: exit 0; 228 tests passed, 1 skipped; `cargo deny` reported `advisories ok, bans ok, licenses ok, sources ok`; `spec_tool.py` reported 0 findings in 60 files; contrast reported 0 failures; `tasks_index.py --check` reported 0 problems in 132 tasks.
- `cargo fmt --all`, `git diff --check`, `python3 scripts/spec_tool.py check`, and `python3 scripts/tasks_index.py --check` passed.
- `psql "$KINEVO_TEST_DATABASE_URL" -v ON_ERROR_STOP=1 -Atqc "SELECT count(*), count(*) FILTER (WHERE EXISTS (SELECT 1 FROM pg_stat_activity a WHERE a.datname = d.datname)) FROM pg_database d WHERE datname ~ '^kinevo_t_[0-9]+_[0-9]+$';"` returned `0|0` after the identity package suite and after `just check`.
- A cleanup audit found 210 old `kinevo_t_<pid>_<sequence>` databases and zero active sessions; those harness-owned stale databases were removed. The static `OnceCell<TestApp>` fixtures that caused additional leaks were replaced by per-test fixtures.
- `cargo install --locked cargo-sweep`: installed cargo-sweep 0.8.0. The required `cargo sweep --time 7` is deferred to the M1 milestone boundary (D-104).

### Environment-limited and failed attempts

- An initial `just check` without `KINEVO_TEST_DATABASE_URL` failed when the legacy testcontainers path returned `disposable PostgreSQL test database should start: Unavailable` (11 DB-backed login tests failed; 26 tests were not run). After D-102, tests attach to the shared server; the complete command above passed.
- The test-first database-cleanup assertion initially failed with `test database kinevo_t_256381_1 should be dropped`; after `DatabaseLease::drop` was changed to wait for cleanup, the named regression test passed.
- An intermediate `just check` caught Clippy's `clippy::panic` and `clippy::inconsistent_struct_constructor` lints in the cleanup and reordered test harness. A narrow reasoned `clippy::panic` allowance was applied to the test-only cleanup failure path, constructor fields were ordered consistently, and the final `just check` passed.
- The successful gate used offline Cargo dependency/advisory caches (`CARGO_NET_OFFLINE=true`); network access to the advisory database was not required.
- No Android emulator, browser E2E, or external provider checks are required by T-024 and were not run.

### Deviations

- The T-024 implementation exceeds the 330-line guidance. D-55 makes the line budget advisory; the generated Cargo.lock change is excluded. No acceptance test was weakened.
- Added `AC-9` for Android one-session logout, because D-37's body-token transport otherwise had no way to sign out of one session. The additional behavior and its spec updates are recorded in D-100.
- T-011's recorded Testcontainers choice (D-58) is superseded by run-wide D-102: all database tests now attach to one shared PostgreSQL server, and each `TestApp` gets a uniquely named database. No testcontainers fallback remains.
- D-102 required additional test-harness edits beyond T-024's original Files list: `DatabaseLease` now synchronously removes its database and fails the test if cleanup fails; the identity login, refresh, and refresh HTTP tests no longer retain `TestApp` in static `OnceCell`s, so every test owns and drops a unique database. Harness field order releases service pool clones before the database lease.
- Run-wide D-103 and D-104 add the root development profile and milestone cache-sweep workflow. Additional edits to `AGENTS.md`, the test harness, scripts, and testing/dependency specs are required by those instructions.
- `serde_json` is now a normal `kinevo-mod-identity` dependency because the Android request body is parsed in library code; it was already approved in `spec/standards/open-source-stack.md`.
- Login wiring in `http/login.rs` now selects the cookie name and `Secure` attribute from the configured public URL; the standard header implementation follows D-96 and adds no cookie crate.

### Decisions made by implementer

| ID | Choice and alternatives | Files and reversal |
|---|---|---|
| D-100 | Android `POST /auth/logout` consumes `{ refresh_token }`, revokes that family, returns 204, and never sets a cookie. Requiring a cookie or limiting Android to logout-all would prevent one-session sign-out. Pending owner review. | `spec/decisions.md`, `spec/standards/auth.md`, `spec/modules/identity.md`, task AC-9, Android handler/test. Reverse by changing this contract and removing the body-token route behavior. |
| D-101 | Web cookie requests require exact public `Origin`; Android body-token requests require the Android marker, reject cookies, and may omit `Origin`, with browser CORS limited to D-37's shell origin. Requiring the public server origin would reject the shell. Pending owner review. | `spec/decisions.md`, `spec/standards/auth.md`, task Questions, existing T-019 OriginGuard contract. Reverse by changing the shared origin contract and its T-019 tests. |
| D-102 | All DB tests use one externally started shared PostgreSQL server and unique per-test databases; this supersedes D-58 and T-011's testcontainers requirement. | `spec/decisions.md`, `spec/12-testing.md`, `spec/standards/open-source-stack.md`, `crates/kinevo-db`, preflight and dev-Postgres scripts. Reverse by restoring a container-backed test harness and the corresponding stack entry. |
| D-103 | Root dev profile uses `debug = 0`; `CARGO_PROFILE_DEV_DEBUG=2` restores local debug info. | `Cargo.toml`, `spec/decisions.md`. Reverse by removing the profile override. |
| D-104 | Install cargo-sweep once and run `CARGO_TARGET_DIR=/home/kepolu/project/open-source/nivaras/Kinevo/target cargo sweep --time 7` at each milestone end; no `cargo clean`. | `spec/decisions.md`, `spec/standards/open-source-stack.md`; sweep is pending M1 integration. Reverse by removing this milestone-boundary maintenance rule. |

### Files touched

Identity refresh/logout handlers, cookie and response adapters, refresh service, identity login/refresh/refresh HTTP tests and Cargo manifest; shared DB test harness and manifests; root Cargo profile; `scripts/dev-postgres.sh`, `scripts/preflight.sh`; `AGENTS.md`; task report/index; and the decisions, identity, auth, testing, and stack specifications. Cargo.lock was regenerated after removing testcontainers.

## Questions

- Q-1: The Android body-token flow in D-37 defines login and refresh, but not single-session logout. Resolved by implementer under D-100: Android logout consumes the same `{ refresh_token }` body and returns 204 without `Set-Cookie`, so an Android client can revoke one session. Pending owner review.
- Q-2: `auth.md` says cookie endpoints require the public `Origin`, while T-019's `OriginGuard` explicitly accepts Android body requests without `Origin` and rejects cookies. Resolved by implementer under D-101: enforce exact public `Origin` for web cookie requests; allow Android body requests with the Android marker and no cookie, with browser CORS limited to the D-37 shell origin. Pending owner review.
