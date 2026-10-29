---
id: T-028
title: Implement admin account management, invites and the password-reset flows
milestone: M1
kind: feature
status: review
depends_on: [T-027, T-012, T-014]
requirements: [ID-FR-07, ID-FR-10, ID-FR-13, D-27, D-45, SM-22, SEC-AC-10, API-23]
branch: m1-t028-identity-admin-resets
budget_lines: 430
assignee: "OpenAI Codex"
---

# T-028: Implement admin account management, invites and the password-reset flows

## Goal

An instance admin can list accounts (metadata only), disable or re-enable them, create invites and issue one-time password-reset links. Anyone with a reset link can set a new password. Without SMTP the admin hands over the link; the request endpoint never reveals whether an email exists.

## Context

> **Endpoints** (`identity.md` §2): `POST /auth/password-reset/request` public → 202 always (no existence leak), `rate_limited`. `POST /auth/password-reset/confirm` public → 204; all families revoked; errors `reset_token_invalid` 422, `password_too_short` 422. `GET /admin/accounts` (`identity.admin.accounts.read`) → page of `{id, email, display_name, status, created_at, last_login_at}` — **no content data**. `PATCH /admin/accounts/{id}` `{status}` (`identity.admin.accounts.update`) → 200; `last_admin` 409, `version_conflict`. `POST /admin/invites` `{email?}` (`identity.admin.invite.create`) → 201 `{ invite_url }` (token shown once). `POST /admin/accounts/{id}/password-reset` (`identity.admin.password_reset.issue`) → 201 `{ reset_url }` (token shown once; also emailed if SMTP is configured); `not_found`.
> **ID-FR-13**: Without SMTP, forgot-password is by admin-issued link (D-27); the request endpoint still returns 202 and does nothing. With SMTP, an email with a one-time link (TTL `auth.password_reset.ttl`) is sent; the email contains no account data other than the link.
> **ID-FR-10**: An account cannot delete or disable itself if it is the last active `instance_admin` (`last_admin` 409). **ID-FR-07**: disabling an account revokes all refresh families immediately.
> **SM-22**: one-time tokens: `issued → used` by atomic `UPDATE … WHERE used_at IS NULL AND expires_at > now` returning 1 row; reuse or expiry ⇒ `reset_token_invalid` / `invite_invalid`. Issuing a new reset token invalidates earlier unused ones for that account. Tokens: 256-bit random, base64url 43 chars, stored as SHA-256, shown once.
> **D-45**: these endpoints use `SystemTx` after the permission check: `GET /admin/accounts`, `PATCH /admin/accounts/{id}`, `POST /admin/accounts/{id}/password-reset`, `POST /auth/password-reset/request|confirm`. Invites are instance-level (no RLS): creation requires an admin `Tx::begin_admin(caller)` after `require_admin()`.
> **SEC-AC-10**: `/admin/accounts` returns no content fields (contract test over the response schema). **API-23**: non-admin calling an admin route → 403 `forbidden` (gate, T-017).
> **Policies**: `auth.invite.ttl` (1 h–30 d, default 604800), `auth.password_reset.ttl` (5 m–24 h, default 3600).
> **URLs**: `invite_url = {base_url}/register?invite=<token>`, `reset_url = {base_url}/reset-password?token=<token>` where `base_url` is `InstanceState.base_url` (the web routes are implemented in T-048/T-052).
> **Email**: delivery through the `Mailer` port (implemented in T-030); when no mailer is configured the port is a no-op returning `Delivery::NotConfigured`.
> **Audit/events**: audit `account.status_changed` (details: `from`, `to` enums); event `AccountDisabled` on disable; reset confirmation writes `auth.password_changed` and emits `PasswordChanged`. Invite creation and reset issuance write no audit action (not in ADM-FR-05's list) — record a Question if the owner wants them audited.
> **Admin CLI** (ADM-FR-09, implemented in T-040): `reset-password <email>` prints a one-time link; it calls `ResetService::issue_for_email` defined here.

## Scope

**In:**
- Domain: `domain::reset::ResetToken` (issue/expiry logic, pure), `domain::accounts::can_change_status(actor, target, active_admin_count, new_status) -> Result<(), AppError>` implementing `last_admin` and no self-lockout.
- `app::admin_accounts`: `list(page)` (cursor pagination by `created_at, id` using T-007; `SystemTx`; selects only the six listed columns), `set_status(actor, id, status, version)` (`SystemTx`; version check; on `disabled` revokes all families with reason `admin`, writes audit, emits `AccountDisabled`; counts active admins inside the same transaction with `FOR UPDATE` on the admin rows to avoid a race where two admins disable each other).
- `app::invites::create(admin_tx, email: Option<Email>, ttl)` and URL builder; `list`/`revoke` of invites are **not** in 1.0.
- `app::reset::ResetService`: `issue_for_account(id, created_by: Option<AccountId>)` (`SystemTx`; invalidates earlier unused tokens by setting `used_at = now` on them; stores the hash; returns the plaintext `Secret<String>` once), `issue_for_email(email)` (for the CLI; unknown email → `not_found` CLI-side), `request(email)` (public: if SMTP configured and the account exists and is active → issue + `Mailer.send`; always returns 202; constant work: the same code path executes a dummy hash lookup when the account is missing), `confirm(token, new_password)` (`SystemTx`: atomically consume token → `UPDATE accounts SET password_hash`, bump version, revoke **all** families (`password_changed`), mark used; validate password rules first; wrong/used/expired token → `reset_token_invalid`).
- Handlers and `RouteSpec`s for the six endpoints; admin ones with the listed permissions and `RateClass::Api`, public ones `RateClass::Auth`.
- Jobs: none new (`identity.resets.prune` is T-026).
- `ports::Mailer` trait in `kinevo-ports::platform` (`send(message: MailMessage) -> Result<Delivery, AppError>`, `MailMessage { to: Email, template: MailTemplate, locale: Locale, vars: Vec<(&'static str, String)> }`, `MailTemplate::PasswordReset`) with `NoMailer`. The SMTP implementation is T-030.

**Out:**
- Email templates content and SMTP transport: T-030. Web screens: T-051/T-052.
- Promoting or demoting administrators (CLI only, RBAC-04): T-040.
- Account deletion/export: T-029.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/platform.rs` | modify | `Mailer`, `MailMessage`, `MailTemplate`, `Delivery`; fake `FakeMailer` in `fakes.rs` |
| `crates/kinevo-mod-identity/src/domain/{reset,accounts}.rs` | create | |
| `crates/kinevo-mod-identity/src/app/{admin_accounts,invites,reset}.rs` | create | |
| `crates/kinevo-mod-identity/src/infra/{admin_accounts,resets}.rs` | create | |
| `crates/kinevo-mod-identity/src/infra/invites.rs` | modify | `create` (admin tx) |
| `crates/kinevo-mod-identity/src/http/admin.rs`, `http/reset.rs` | create | |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | routes |
| `crates/kinevo-mod-identity/tests/admin_accounts.rs`, `tests/reset.rs` | create | |

## Interfaces

```rust
pub struct AdminAccountRow { pub id: AccountId, pub email: String, pub display_name: String, pub status: AccountStatus, pub created_at: Instant, pub last_login_at: Option<Instant> }
pub struct IssuedLink { pub url: Secret<String> }   // shown once
#[async_trait] pub trait Mailer: Send + Sync { async fn send(&self, m: MailMessage) -> Result<Delivery, AppError>; }
```

Registry codes: `last_admin` 409, `reset_token_invalid` 422, `invite_invalid` 422 (T-025 consumes), `not_found`.

## Business rules

1. `PATCH /admin/accounts/{id}` accepts `{status}` ∈ `active|disabled` only (`deleting` is set by the owner's deletion flow); the caller's own account may not be disabled when it is the last active admin; any change of the last active admin's status → `last_admin`.
2. Disabling revokes all of the account's families immediately (ID-FR-07); re-enabling does not restore sessions.
3. The list returns exactly the six documented fields, ordered by `created_at DESC, id`; no task/goal/note content exists in the response type.
4. A reset token is valid for `auth.password_reset.ttl`; issuing a new token for the same account invalidates earlier unused ones; confirming marks the token used, changes the password, revokes all sessions.
5. `POST /auth/password-reset/request` returns 202 with an empty body in every case (unknown email, disabled account, SMTP absent, SMTP error).
6. A reset for a `deleting` account is refused as `reset_token_invalid` at confirm time.
7. Invite tokens: expire per `auth.invite.ttl`; `email` optional (when set, registration must use it, T-025).
8. The response contains the link exactly once; it is never stored in plaintext or logged.

## Security

- Admin lists and statuses go through `SystemTx` only after the gate has confirmed `instance_admin`; the repository selects fixed column lists (no `SELECT *`, CQ-11).
- Token and link values are `Secret`; the `201` responses use `Cache-Control: no-store` and `store_response_body = false`.
- Request timing for the public request endpoint must not depend on account existence (do equivalent work; assert with a counting fake).
- Reset confirmation rate-limited by address (`Auth` class); token guesses are infeasible (256-bit).

## Implementation notes

- Counting active admins: `SELECT id FROM accounts WHERE is_instance_admin AND status = 'active' FOR UPDATE` inside `SystemTx`, then decide in the pure function.
- `base_url` is read from `InstanceState`; when it is not set (should not happen after setup) return `service_unavailable`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an admin and 3 accounts **When** `GET /admin/accounts` **Then** the page has the six fields only; a non-admin gets 403 | `admin_accounts::sec_ac_10_metadata_only_and_admin_only` |
| AC-2 | **Given** the response type **When** its JSON schema is inspected **Then** no property outside the six exists (contract test) | `admin_accounts::sec_ac_10_schema_has_no_content_fields` |
| AC-3 | **Given** two active admins **When** both disable each other concurrently **Then** exactly one succeeds and the other gets `last_admin` | `admin_accounts::id_fr_10_last_admin_race` |
| AC-4 | **Given** an account with sessions **When** an admin disables it **Then** 200, all its families are revoked, `AccountDisabled` emitted, audit `account.status_changed`; its next login is `account_disabled` | `admin_accounts::id_fr_07_disable_revokes_sessions` |
| AC-5 | **Given** a stale `version` **When** patching **Then** 409 `version_conflict` | `admin_accounts::version_conflict_on_status_change` |
| AC-6 | **Given** an admin **When** creating an invite **Then** 201 with `invite_url` containing a 43-char token; only the hash is in the table; expiry = now + ttl | `admin_accounts::invite_creation` |
| AC-7 | **Given** an admin **When** issuing a reset **Then** 201 `reset_url`; a second issue invalidates the first | `reset::sm_22_new_token_invalidates_old` |
| AC-8 | **Given** a valid link **When** confirming with a good password **Then** 204, the password works, all families are revoked, `PasswordChanged` emitted; using the link again → 422 `reset_token_invalid`; an expired link (fake clock) → 422 | `reset::sm_22_confirm_once_and_expiry` |
| AC-9 | **Given** an unknown email, a known email without SMTP, and a known email with the fake mailer **When** `request` is called **Then** all return 202 with identical bodies; only the last sends one email whose variables contain the link and nothing else about the account | `reset::id_fr_13_request_never_leaks_existence` |
| AC-10 | **Given** the counting fakes **When** `request` runs for known and unknown emails **Then** the same number of lookups and hash operations happen | `reset::id_fr_13_equivalent_work` |
| AC-11 | **Given** logs and audit after issuing and confirming **When** scanned for the token **Then** it does not appear | `reset::sec_ac_45_no_reset_tokens_in_logs` |

## Tests

```
cargo nextest run -p kinevo-ports -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
just check
```

## Definition of done

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Only the files listed under *Files* changed; the D-55 budget exception is recorded below.
- [x] Report below is filled in.

## Report

### Commands and results

- `eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=1 cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing --test admin_accounts --test reset` — 11 passed, 0 skipped.
- `eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=1 cargo nextest run -p kinevo-ports -p kinevo-mod-identity --features kinevo-db/testing` — 99 passed, 1 skipped.
- `eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=1 just check` — all gates passed; 269 tests passed, 1 skipped; spec check reported 0 findings in 60 files; contrast reported 0 failures; task index reported 0 problems in 132 tasks.
- The first `just check` attempt stopped at `cargo fmt --check` after the final HTTP test edit. `cargo fmt --all` fixed the formatting; the subsequent full check completed.
- `scripts/dev-postgres.sh env` reused the existing PostgreSQL 17 disposable server; no image pull or container creation was needed.
- `cargo deny` emitted existing duplicate-crate and unmatched-license-allowance warnings. Its advisories, bans, licenses, and sources checks completed successfully. Clippy emitted the existing `rand::thread_rng` configuration warning; lint passed after fixing the T-028 lint findings.

### Environment-limited

- None.

### Deviations

- D-108 adds the injectable reset-account lookup required to count AC-10 lookup and dummy-verification work. Production uses one SQLx `SystemTx` query; this implementation choice is pending architect review.
- The task `budget_lines` value was treated as guidance, consistent with D-55.

### Files touched

- `crates/kinevo-mod-identity/src/app/{admin_accounts,invites,reset}.rs`
- `crates/kinevo-mod-identity/src/domain/{accounts,reset}.rs`
- `crates/kinevo-mod-identity/src/http/{admin,reset}.rs`
- `crates/kinevo-mod-identity/src/infra/{admin_accounts,resets}.rs`, `src/infra/invites.rs`
- `crates/kinevo-mod-identity/src/{app,domain,http,infra}/mod.rs`, `src/lib.rs`
- `crates/kinevo-mod-identity/tests/{admin_accounts,reset}.rs`
- `crates/kinevo-ports/src/{platform,fakes}.rs`
- `spec/decisions.md`, `spec/index.md`

## Questions

- D-108 — Resolved by implementer, pending architect review: inject `ResetAccountLookup` so AC-10 can count account lookups and dummy password work. The production adapter uses one `SystemTx` query.
