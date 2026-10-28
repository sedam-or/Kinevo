---
id: T-024
title: Implement POST /auth/refresh, /auth/logout, /auth/logout-all and the refresh cookie
milestone: M1
kind: feature
status: in_progress
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

> **Endpoints** (`identity.md` §2): `POST /auth/refresh` — cookie (web) or body `{ refresh_token }` (`X-Kinevo-Client: android`) → 200 `{ access_token, expires_in }` + rotated cookie (web) or `+ refresh_token` in the body (android); errors `unauthenticated` 401, `refresh_reuse_detected` 401, `csrf_check_failed` 403. `POST /auth/logout` — cookie → 204, family revoked, cookie cleared, idempotent. `POST /auth/logout-all` — permission `identity.session.manage` → 204, all families revoked.
> **Cookie** (`auth.md` §4): `__Secure-kinevo_rt`: `HttpOnly; Secure; SameSite=Strict; Path=/api/v1/auth; Max-Age=<remaining absolute lifetime>`; no `Domain`; not `__Host-` because the narrow path is required. Required headers on cookie endpoints: `X-Kinevo-Client: web` (or `android` for the body flow) and `Origin` equal to the public URL.
> **API-11 / SEC-09**: `POST /auth/refresh` and `/auth/logout` require the header and a matching `Origin` (T-019 `OriginGuard`, `cookie_endpoint: true`).
> **Android flow** (`auth.md` §5, D-37): `POST /auth/refresh` with `X-Kinevo-Client: android` and body `{ "refresh_token": "…" }`; rotation/reuse/grace identical; the response body returns the new `refresh_token`; no `Set-Cookie`.
> **ID-FR-06**: Access tokens are held only in JS memory; a page reload restores the session by calling `/auth/refresh` (E2E in T-056).
> **SEC-AC-04**: refresh token replay from a stolen copy → family revoked, attacker and victim must re-login, audit `auth.refresh_reuse`. **SEC-AC-05**: CSRF against `/auth/refresh` from another origin → 403 `csrf_check_failed`.
> **Services**: `RefreshService` (T-022) implements the rotation; `session_response` (T-023) delivers tokens; revocation helpers exist in `infra::sessions`.
> **Rate limit**: refresh and logout are authenticated by cookie only, so they use class `Auth` (per address); `logout-all` is bearer-authenticated and uses `Api`.

## Scope

**In:**
- Handlers and `RouteSpec`s: `POST /auth/refresh` (`Access::Public`, `RateClass::Auth`, `cookie_endpoint: true`, `store_response_body: false`), `POST /auth/logout` (`Public`, `cookie_endpoint: true`), `POST /auth/logout-all` (`Permission("identity.session.manage")`, `RateClass::Api`).
- Cookie helpers `http::cookie`: `build_refresh_cookie(token, remaining_seconds, secure: bool) -> Cookie`, `clear_refresh_cookie()`, `read_refresh_cookie(&CookieJar)`; `secure` follows the public URL scheme (https → `Secure`; the `__Secure-` prefix requires `Secure`, so in plain-http dev the cookie name falls back to `kinevo_rt` and `SameSite=Strict` stays; document this; tests cover both).
- Logout: revoke the presented token's family with reason `logout`; if the cookie is missing or unknown, still 204 and clear the cookie (idempotent).
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

_Filled in by the implementer (WORKFLOW.md §4)._

## Questions

_None yet._
