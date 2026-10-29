---
id: T-023
title: Implement POST /auth/login with lockout, constant-work verification and MFA hook
milestone: M1
kind: feature
status: review
depends_on: [T-022, T-021, T-014, T-017, T-018, T-019]
requirements: [ID-FR-03, ID-FR-04, ID-FR-07, SEC-AC-03, SEC-07, API-14, AUTH-05]
branch: m1-t023-identity-login
budget_lines: 420
assignee: Codex
---

# T-023: Implement POST /auth/login with lockout, constant-work verification and MFA hook

## Goal

A user can sign in with email and password. Failures are indistinguishable between unknown email and wrong password, accounts lock after repeated failures, and the second factor is requested when enabled. The endpoint returns the account profile and an access token and issues the first refresh token.

## Context

> **Endpoint** (`identity.md` §2): `POST /auth/login` public → 200 `{ account, access_token, expires_in }` + refresh cookie (web) or `refresh_token` in the body with `X-Kinevo-Client: android`. Errors: `invalid_credentials` 401, `mfa_required` 401 (`details.methods`), `invalid_mfa_code` 401, `account_locked` 429 (`Retry-After`), `account_disabled` 403, `rate_limited` 429.
> **Data flow — login** (`identity.md` §5): 1. rate-limit by address (`ratelimit.auth_per_minute`); 2. system-context repository loads the account by normalised email (or none); 3. constant-work verification: argon2id verify against hash or a dummy hash; 4. if lock active → 429 `account_locked`; if password wrong → increment counter (system tx), maybe lock, audit `auth.login_failed` (details: reason code only), return 401 `invalid_credentials`; 5. if TOTP enabled and no valid code supplied → 401 `mfa_required` (no counter change); wrong code → counts as a failed attempt; 6. one transaction: reset counter, create family + first refresh token, audit `auth.login`, outbox `AccountLoggedIn`; 7. response: access token in body; refresh token in cookie.
> **ID-FR-03**: Failure indistinguishable for unknown email vs wrong password (same status, body, comparable latency; dummy verification). **ID-FR-04**: after `auth.lockout.max_attempts` failed attempts in `auth.lockout.window` the account is locked for `auth.lockout.duration`; correct credentials during lock still return `account_locked`; a successful login resets the counter. Locked + correct password → still `account_locked` (no oracle). Unknown email: counted per (email hash) in memory for rate limiting only, never persisted.
> **State machine** (`identity.md` §4): `idle ──bad credentials──▶ counting(n) ──n ≥ max within window──▶ locked(until)`; `locked` ──time ≥ until──▶ idle; good credentials reset.
> **API-14**: 401 on login is reserved for credential failures of this endpoint; the `account_disabled` case is 403.
> **Policies**: `auth.lockout.max_attempts` (3–20, default 5), `auth.lockout.window` (60–3600, 900), `auth.lockout.duration` (60–86400, 900), `auth.totp.required` (bool), `auth.access_token.ttl`, refresh TTLs (via `SessionPolicy`).
> **AUTH-05 / SEC-AC-45**: no credential, token or hash in any log, error body, audit detail or metric.
> **Validation** (`identity.md` §6): `email` kind; `password` `secret_text` (never trimmed, never logged); `totp code` `digits` exactly 6 (or a recovery code, see T-027: 10 characters from the recovery alphabet). A login request body is `{ email, password, totp_code?, recovery_code? }` (`deny_unknown_fields`).
> **Events** (`identity.md` §7): emits `AccountLoggedIn` (payload ids only). **Audit**: `auth.login` (subject = account), `auth.login_failed` (subject = account if known; `details.reason` ∈ `bad_password`, `unknown_email`, `bad_mfa`, `locked`; for unknown email the subject is null and no email is stored).
> **Response profile** (`GET /account`): `id, email, display_name, locale, timezone, week_start, totp_enabled, is_instance_admin, version`.

## Scope

**In:**
- `domain::lockout`: pure `LockoutState { failed_count, window_start, locked_until }` and `on_failure(state, now, policy) -> LockoutState`, `on_success() -> LockoutState`, `is_locked(state, now)`, exhaustive table test (state × event).
- `infra::accounts` repository (system context): `find_by_email(tx: &mut SystemTx, &Email) -> Option<AccountRow>`, `record_failure(tx, id, new_state)`, `record_success(tx, id, now)` (sets `last_login_at`).
- `app::login::LoginService { db, hasher, sessions, mfa: Arc<dyn MfaVerifier>, audit, outbox, subscribers, clock, ids, tokens, codec }` with `login(req: LoginRequest, client: Client, addr: ClientAddr, policy: LoginPolicy) -> Result<LoginOutcome, AppError>` following the flow above. `LoginOutcome { profile: AccountProfile, access_token, expires_in, refresh_token: Secret<String> }`.
- Port `MfaVerifier` in `app` (identity-internal trait, implemented by T-027): `async fn verify(&self, account: AccountId, input: MfaInput, now) -> MfaResult::{Valid, Invalid}`; `FakeMfa` for tests.
- `http::auth::login` handler + `RouteSpec` (`POST /auth/login`, module `identity`, `Access::Public`, `RateClass::Auth`, `store_response_body: false`) and `LoginDto`/`LoginResponse` types with `utoipa` derives where the OpenAPI task expects them (annotations only; assembly is T-041).
- Cookie and Android token delivery are shared helpers implemented in T-024; this task calls `session_response(client, refreshed)` (declared in T-024). To keep tasks independent, T-023 declares the helper's signature in `http/session_response.rs` as a thin function that for `Client::Web` sets the cookie via `CookieJar` and for `Client::Android` puts `refresh_token` in the body; T-024 owns the **cookie attributes tests** only.
- In-memory unknown-email attempt counter is **not** needed beyond the address rate limit (the spec says per-email-hash in memory for rate limiting only): implement `UnknownEmailTracker` (moka, bounded 100 000, TTL = lockout window) keyed by SHA-256 of the normalised email, used only to throttle (`rate_limited`) after `max_attempts` within the window; it never returns `account_locked`.

**Out:**
- Refresh/logout endpoints and cookie attribute tests: T-024. TOTP enrolment and verification: T-027. Registration: T-025.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/domain/lockout.rs` | create | |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | create | system-context queries |
| `crates/kinevo-mod-identity/src/app/login.rs` | create | |
| `crates/kinevo-mod-identity/src/app/mfa.rs` | create | trait + fake |
| `crates/kinevo-mod-identity/src/http/login.rs`, `http/session_response.rs`, `http/dto.rs` | create | |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | `router()` includes the route |
| `crates/kinevo-mod-identity/tests/login.rs` | create | component and HTTP tests |

## Interfaces

```rust
pub struct LoginRequest { pub email: String, pub password: Secret<String>, pub totp_code: Option<String>, pub recovery_code: Option<Secret<String>> }  // deny_unknown_fields; validated in `validate`
pub enum LoginError /* → AppError */ { InvalidCredentials, MfaRequired { methods: Vec<&'static str> }, InvalidMfaCode, AccountLocked { retry_after_seconds: u32 }, AccountDisabled }
#[async_trait] pub trait MfaVerifier: Send + Sync { async fn verify(&self, account: AccountId, input: &MfaInput, now: Instant) -> MfaResult; }
```

Status mapping: `invalid_credentials` 401, `mfa_required` 401 (`details.methods = ["totp","recovery_code"]`), `invalid_mfa_code` 401, `account_locked` 429 with `Retry-After`, `account_disabled` 403 (only after correct credentials; a disabled account with wrong credentials looks like any failure).

## Business rules

1. Unknown email: run `verify_dummy`, respond `invalid_credentials`, audit `auth.login_failed` with `reason = unknown_email` and no subject.
2. Wrong password: count the failure inside the window (a failure after `window` elapsed starts a new window); reaching `max_attempts` sets `locked_until = now + duration`; return `invalid_credentials` (the response of the attempt that triggers the lock is still `invalid_credentials`; later attempts return `account_locked`).
3. Locked: always `account_locked` with `Retry-After = seconds until locked_until`, regardless of password correctness; no counter change; the password is still verified (constant work) but never confirmed.
4. TOTP enabled and no code supplied → `mfa_required` (no counter change, no session). Wrong code → counts as a failure; `invalid_mfa_code`.
5. `auth.totp.required = true` and the account has no TOTP: login succeeds but the profile and a response field `mfa_enrol_required: true` tell the client to enrol (T-027/web); the access token carries `mfa = false`.
6. Success: reset counter, set `last_login_at`, upgrade the password hash if `OkNeedsRehash`, create family (with `device_label` from the user agent and the `mfa` flag), audit, outbox event — in one `SystemTx`; commit; respond.
7. A `disabled`/`deleting` account with correct credentials → 403 `account_disabled`; no family is created.

## Security

- The response and the audit never reveal whether an email exists beyond the accepted `unknown_email` audit reason (audit is admin-only).
- Passwords and TOTP codes are never logged or placed in audit details (AUTH-05).
- Tokens in the response body only for `Client::Android` (D-37); the web flow uses the cookie.
- Lockout state is per account in the database; the unknown-email tracker is in-memory only and keyed by hash.

## Implementation notes

- Do the password verification **before** any early return that depends on account existence or lock state, so latency does not depend on them.
- The user-agent `device_label` helper (`domain::device::label(user_agent, client)`) produces strings such as `Firefox on Linux`, `Chrome on Android`, `Nivaras Android`, else `Unknown device`; it never stores the raw user agent.
- `SystemTx` is used for lookup, counters and the final write. The final write (family, token, audit, outbox) is in the same `SystemTx` as `record_success`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a registered account **When** logging in with correct credentials **Then** 200 with `account`, `access_token`, `expires_in`, a refresh cookie (web) and one family/token row; `auth.login` audited; `AccountLoggedIn` in the outbox | `login::id_login_success_flow` |
| AC-2 | **Given** unknown email and wrong password **When** both log in **Then** identical status, headers (except request id) and body | `login::id_fr_03_unknown_vs_wrong_password_identical` |
| AC-3 | **Given** the counting hash backend **When** the unknown-email path runs **Then** one verification occurred (constant work) | `login::id_fr_03_unknown_email_does_dummy_work` |
| AC-4 | **Given** max 5/window 900/duration 900 and a fake clock **When** 5 wrong passwords are sent **Then** attempts 1–5 return `invalid_credentials`, the 6th `account_locked` with `Retry-After`, a correct password during the lock still `account_locked`, and after the duration login succeeds and resets the counter | `login::id_fr_04_lockout_lifecycle` |
| AC-5 | **Given** failures spaced beyond the window **When** counted **Then** the counter restarts and no lock occurs | `login::id_fr_04_window_resets_counter` |
| AC-6 | **Given** TOTP enabled (fake verifier) **When** logging in without a code **Then** 401 `mfa_required` with `details.methods` and no counter change; with a wrong code 401 `invalid_mfa_code` and counter +1; with a valid code 200 and `mfa = true` in the access token | `login::mfa_branches` |
| AC-7 | **Given** a disabled account **When** correct credentials are used **Then** 403 `account_disabled` and no family row | `login::account_disabled_after_correct_credentials` |
| AC-8 | **Given** the lockout state machine **When** table-tested over all (state × event) pairs **Then** transitions match the diagram | `lockout::tests::id_fr_04_state_machine_table` |
| AC-9 | **Given** an old-parameter hash **When** login succeeds **Then** the stored hash is upgraded | `login::password_rehash_on_login` |
| AC-10 | **Given** 6 requests/minute from one address to `/auth/login` **When** sent through the full HTTP stack **Then** the 6th is 429 `rate_limited` (T-018) before any password work | `login::sec_ac_03_rate_limit_before_work` |
| AC-11 | **Given** captured logs and audit details after failed and successful logins with canary passwords **When** scanned **Then** no canary appears | `login::sec_ac_45_no_credentials_in_logs_or_audit` |
| AC-12 | **Given** `X-Kinevo-Client: android` **When** logging in **Then** the body contains `refresh_token` and there is no `Set-Cookie` | `login::d_37_android_receives_token_in_body` |

## Tests

```
cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
just test-slow   # includes SEC-AC-03 timing test if present
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

### Summary

`POST /auth/login` now verifies credentials with constant work, applies account and unknown-email throttling, handles lockout and MFA branches, and creates the first session atomically. Web and Android receive the refresh token through their specified channels; the endpoint records only safe audit details.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `login::id_login_success_flow` | pass |
| AC-2 | `login::id_fr_03_unknown_vs_wrong_password_identical` | pass |
| AC-3 | `login::id_fr_03_unknown_email_does_dummy_work` | pass |
| AC-4 | `login::id_fr_04_lockout_lifecycle` | pass |
| AC-5 | `login::id_fr_04_window_resets_counter` | pass |
| AC-6 | `login::mfa_branches` | pass |
| AC-7 | `login::account_disabled_after_correct_credentials` | pass |
| AC-8 | `lockout::tests::id_fr_04_state_machine_table` | pass |
| AC-9 | `login::password_rehash_on_login` | pass |
| AC-10 | `login::sec_ac_03_rate_limit_before_work` | pass |
| AC-11 | `login::sec_ac_45_no_credentials_in_logs_or_audit` | pass |
| AC-12 | `login::d_37_android_receives_token_in_body` | pass |

### Commands run

| Command | Exit | Result |
|---|---:|---|
| `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing` (test-first, before implementation) | 101 | Expected red compile: the login, MFA and lockout modules had not yet been implemented. |
| `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing` (after implementation) | 0 | 41 tests passed; 1 skipped. |
| `NEXTEST_TEST_THREADS=1 just test-slow` | 0 | `sec_ac_03_timing_oracle`: 1 passed; 219 skipped. |
| `NEXTEST_TEST_THREADS=1 just check` (default online `cargo deny`) | 101 | RustSec advisory database fetch failed: `fatal: unable to access 'https://github.com/RustSec/advisory-db/': Could not resolve host: github.com`. |
| `cargo-deny --offline check` | 0 | `advisories ok, bans ok, licenses ok, sources ok`; cached database used. Existing duplicate-version and unused-license warnings remain. |
| `shimdir=/tmp/kinevo-deny-shim.AcfkfT; eval "$(scripts/dev-postgres.sh env)"; PATH="$shimdir:/home/kepolu/.cargo/bin:$PATH" NEXTEST_TEST_THREADS=1 just check` | 0 | Full `just check` passed with the unchanged recipe and a temporary wrapper that strips Cargo's `deny` dispatch argument and adds `--offline`: format, Clippy, 219 tests passed/1 skipped, cargo-deny, spec, contrast, and task-index checks. |
| `python3 scripts/spec_tool.py check` | 0 | `0 finding(s) in 60 spec files`. |
| `python3 scripts/tasks_index.py` | 0 | Regenerated `tasks/index.md`; `0 problem(s) in 132 task(s)`. |
| `python3 scripts/tasks_index.py --check` | 0 | `0 problem(s) in 132 task(s)`. |
| `git diff --check` | 0 | Clean. |
| `cargo info axum-extra@0.10.3` | 101 | Could not resolve `index.crates.io` after three retries; no dependency was added. |

### Files changed

Counts are relative to `origin/dev` and include new files; `Cargo.lock` is excluded from the task budget per D-55.

| Path | Added | Removed |
|---|---:|---:|
| `Cargo.lock` | 28 | 0 |
| `crates/kinevo-http/src/problem.rs` | 6 | 4 |
| `crates/kinevo-kernel/src/errors/mod.rs` | 1 | 0 |
| `crates/kinevo-mod-identity/Cargo.toml` | 6 | 0 |
| `crates/kinevo-mod-identity/src/app/mod.rs` | 2 | 0 |
| `crates/kinevo-mod-identity/src/app/login.rs` | 656 | 0 |
| `crates/kinevo-mod-identity/src/app/mfa.rs` | 21 | 0 |
| `crates/kinevo-mod-identity/src/domain/mod.rs` | 2 | 0 |
| `crates/kinevo-mod-identity/src/domain/device.rs` | 50 | 0 |
| `crates/kinevo-mod-identity/src/domain/lockout.rs` | 168 | 0 |
| `crates/kinevo-mod-identity/src/http/mod.rs` | 4 | 0 |
| `crates/kinevo-mod-identity/src/http/dto.rs` | 97 | 0 |
| `crates/kinevo-mod-identity/src/http/login.rs` | 86 | 0 |
| `crates/kinevo-mod-identity/src/http/session_response.rs` | 27 | 0 |
| `crates/kinevo-mod-identity/src/infra/mod.rs` | 1 | 0 |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | 176 | 0 |
| `crates/kinevo-mod-identity/src/infra/hasher.rs` | 21 | 0 |
| `crates/kinevo-mod-identity/src/lib.rs` | 4 | 3 |
| `crates/kinevo-mod-identity/tests/login.rs` | 961 | 0 |
| `spec/decisions.md` | 4 | 0 |
| `spec/index.md` | 1 | 1 |
| `tasks/T-023-identity-login.md` | 98 | 3 |
| `tasks/index.md` | 2 | 2 |

### Deviations

- The task describes `CookieJar`; the response helper writes a validated `Set-Cookie` header using Axum's existing header types. The `axum-extra` crate was not in the approved stack, and its registry lookup failed due DNS; adding an unverified dependency was avoided (D-96, pending owner review).
- The task's file list omits small wiring and required helpers: identity module exports, `infra/hasher.rs`, `domain/device.rs`, dependency declarations/lockfile, the shared error response mapping, and the `reason` audit-detail registry entry. They are needed for the specified endpoint and test coverage.
- The diff totals 2,422 additions and 13 removals against `origin/dev`; excluding 28 generated `Cargo.lock` additions, that is 2,394 additions and 13 removals versus the approximate 420-line budget. D-55 makes this guidance rather than a blocker; the production flow, component/HTTP acceptance coverage, and four traceable decisions account for the additional lines.
- Network access prevented the default cargo-deny advisory fetch and the `axum-extra` registry lookup. The cached advisory database passed offline; exact commands and errors are above.

### Decisions made by implementer

| ID | Choice; alternatives rejected; reason | Files touched | Reversal |
|---|---|---|---|
| D-96 (pending owner review) | Use validated standard `Set-Cookie`; reject adding an unverified `axum-extra` dependency or delaying the endpoint because the registry was unreachable. | `spec/decisions.md`, `spec/index.md`, `tasks/T-023-identity-login.md`, `crates/kinevo-mod-identity/src/http/session_response.rs` | Adopt an approved cookie dependency and replace the helper/tests. |
| D-97 (superseded by D-107) | The initial implementation selected `23456789ABCDEFGHJKLMNPQRSTUVWXYZ`; T-027 AC-10 requires excluding `L` and `U`, so D-107 now governs generated and accepted recovery codes. | `spec/decisions.md`, `spec/index.md`, `spec/standards/auth.md`, `tasks/T-023-identity-login.md`, `crates/kinevo-mod-identity/src/http/dto.rs` | Supersede D-107 with a later decision and update both enrolment and login validation together. |
| D-98 (pending owner review) | Key the bounded in-memory unknown-email tracker with SHA-256 of normalized email; reject storing the email or adding a keyed-hash dependency. | `spec/decisions.md`, `spec/index.md`, `tasks/T-023-identity-login.md`, login implementation/tests | Replace the key derivation and tests if the security standard selects another digest. |
| D-99 (pending owner review) | Inject a narrow `PasswordWork` port so tests count dummy/real verification while production delegates to the existing bounded Argon2 hasher; reject weakening the constant-work test. | `spec/decisions.md`, `spec/index.md`, `tasks/T-023-identity-login.md`, login implementation/tests | Replace the port with an equivalent test seam that preserves the same constant-work evidence. |

### Spec issues noticed

The TOTP recovery alphabet is now fixed by D-107 after applying T-027 AC-10 to the earlier pending choice D-97. The task requests `CookieJar` without listing or approving the required crate; D-96 records the standard-header implementation pending owner review.

### Not done / not verified

- The default online cargo-deny fetch could not reach GitHub. The unchanged `just check` recipe passed with the local advisory database in offline mode; the database freshness could not be checked.
- `axum-extra` availability could not be verified because crates.io DNS failed; no dependency was introduced.
- Cookie attribute-specific assertions remain assigned to T-024, as specified by this task.

### Follow-up suggestions

Review D-96 through D-99 when reviewing this task; T-024 should cover cookie attributes.

## Questions

_None yet._
