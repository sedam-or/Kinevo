# Module `identity`

Kind: core. Depends on: —. Tables: `accounts`, `refresh_token_families`, `refresh_tokens`, `recovery_codes`,
`password_resets`, `invites`, `account_exports` (`06-domain-model.md` §4). Prefix: `ID`.
Cross-spec: `standards/auth.md` (token mechanics), `standards/security-by-design.md`, `07-api-conventions.md` §2.

## 1. Permissions

| Key | `account_owner` | `instance_admin` |
|---|---|---|
| `identity.session.manage` (own sessions) | ✓ | ✓ |
| `identity.account.read` / `identity.account.update` (self) | ✓ | ✓ |
| `identity.account.delete` (self) | ✓ | ✓ |
| `identity.account.export` (self) | ✓ | ✓ |
| `identity.totp.manage` (self) | ✓ | ✓ |
| `identity.admin.accounts.read` / `.update` | — | ✓ |
| `identity.admin.invite.create` | — | ✓ |
| `identity.admin.password_reset.issue` | — | ✓ |

## 2. Endpoints

| Method / path | Permission | Success | Errors |
|---|---|---|---|
| `POST /auth/register` | public | 201 `{ account, access_token, expires_in }` + refresh cookie | `registration_closed` 403, `invite_invalid` 422, `email_taken` 409, `password_too_short` / `password_too_common` / validation 422, `rate_limited` 429 |
| `POST /auth/login` | public | 200 `{ account, access_token, expires_in }` + cookie | `invalid_credentials` 401, `mfa_required` 401 (`details.methods`), `invalid_mfa_code` 401, `account_locked` 429 (`Retry-After`), `account_disabled` 403, `rate_limited` 429 |
| `POST /auth/refresh` | cookie (web) or body `{ refresh_token }` (`X-Kinevo-Client: android`) | 200 `{ access_token, expires_in }` + rotated cookie (web) or `+ refresh_token` in the body (android) | `unauthenticated` 401, `refresh_reuse_detected` 401, `csrf_check_failed` 403 |
| `POST /auth/logout` | cookie | 204; family revoked; cookie cleared | — (idempotent) |
| `POST /auth/logout-all` | `identity.session.manage` | 204; all families revoked | — |
| `POST /auth/password-reset/request` | public | 202 always (no existence leak) | `rate_limited` |
| `POST /auth/password-reset/confirm` | public | 204; all families revoked | `reset_token_invalid` 422, `password_too_short` 422 |
| `GET /account` | `identity.account.read` | 200 account profile (`id, email, display_name, locale, timezone, week_start, totp_enabled, is_instance_admin, version`) | — |
| `PATCH /account` (If-Match) | `identity.account.update` | 200 | `version_conflict` 409, `timezone_invalid` 422, validation 422 |
| `POST /account/password` | `identity.account.update` | 204; other families revoked | `current_password_incorrect` 422, `password_too_short` 422 |
| `POST /account/totp/enroll` | `identity.totp.manage` | 200 `{ otpauth_uri, recovery_codes[10] }` (shown once; TOTP not yet active) | `totp_already_enabled` 409 |
| `POST /account/totp/confirm` `{code}` | same | 204; `totp_enabled=true` | `totp_code_invalid` 422 |
| `DELETE /account/totp` `{password, code}` | same | 204 | `current_password_incorrect` 422, `totp_code_invalid` 422, `totp_required_by_policy` 409 |
| `GET /account/sessions` | `identity.session.manage` | 200 list of families (`id, device_label, created_at, last_used_at, current`) | — |
| `DELETE /account/sessions/{id}` | same | 204 | `not_found` 404 |
| `POST /account/export` | `identity.account.export` | 202 `{ export_id }` | `export_in_progress` 409 |
| `GET /account/export/{id}` | same | 200 `{ state, size_bytes?, expires_at }` | `not_found` |
| `GET /account/export/{id}/download` | same | 200 `application/zip` | `export_not_ready` 409, `export_expired` 410 |
| `DELETE /account` `{password, confirm: "delete my account"}` | `identity.account.delete` | 202; state `deleting`; sessions revoked | `current_password_incorrect` 422, `last_admin` 409 |
| `GET /admin/accounts` | `identity.admin.accounts.read` | 200 page of `{id, email, display_name, status, created_at, last_login_at}` — **no content data** | `forbidden` 403 |
| `PATCH /admin/accounts/{id}` `{status}` | `…update` | 200 | `last_admin` 409, `version_conflict` |
| `POST /admin/invites` `{email?}` | `…invite.create` | 201 `{ invite_url }` (token shown once) | — |
| `POST /admin/accounts/{id}/password-reset` | `…password_reset.issue` | 201 `{ reset_url }` (token shown once; also emailed if SMTP configured) | `not_found` |

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| ID-FR-01 | Passwords are hashed with argon2id (parameters from `standards/auth.md`) and never logged or returned. | Given register, then `password_hash` is a PHC string starting `$argon2id$`; log scan test finds no password. |
| ID-FR-02 | Password rules: length between `auth.password.min_length` and 128, Unicode NFKC-normalised before hashing, not in the embedded common-password list; **no** composition rules. | Table test incl. 128/129 chars, emoji, common password `password1234`. |
| ID-FR-03 | Login failure is indistinguishable for unknown email vs wrong password (same status, body, and comparable latency: a dummy argon2id verification runs for unknown emails). | Timing test: median difference < 20 % over 200 samples (SEC-AC-03). |
| ID-FR-04 | Lockout: after `auth.lockout.max_attempts` failed attempts in `auth.lockout.window` the account is locked for `auth.lockout.duration`; correct credentials during lock still return `account_locked`. A successful login resets the counter. | State machine §4; Test with injected clock. |
| ID-FR-05 | Refresh tokens rotate on every use. Presenting an already-used token revokes the whole family and returns `refresh_reuse_detected`; the legitimate client must log in again. Concurrent refresh by the same client within `auth.refresh.grace` returns the *same* new token pair (idempotent), to survive double-submit races without false reuse alarms. | Tests: sequential reuse → revoke; concurrent double-submit → no revoke. |
| ID-FR-06 | Access tokens are short-lived (`auth.access_token.ttl`), held only in JS memory; a page reload restores the session by calling `/auth/refresh`. | E2E: reload keeps the session; localStorage/sessionStorage hold no token. |
| ID-FR-07 | Changing password or disabling an account revokes all refresh families immediately; access tokens die within their TTL (documented residual window). | Test. |
| ID-FR-08 | TOTP (RFC 6238, SHA-1, 6 digits, 30 s, ±1 step) is optional per account unless `auth.totp.required`. Seeds are stored as the account secret `identity.totp_seed` through the `SecretStore` port (AEAD, D-40). A used code cannot be reused in the same step (replay cache keyed by account + step). 10 single-use recovery codes are issued at enrolment, stored hashed. | Tests: replay of the same code fails; recovery code works once. |
| ID-FR-09 | Registration obeys `auth.registration.mode`: `closed` → only the setup wizard creates the first account; `invite` → valid unexpired unused invite token required; `open` → anyone (rate-limited). The first account of an instance is `instance_admin`. | Table test across modes. |
| ID-FR-10 | An account cannot delete or disable itself if it is the last active `instance_admin` (`last_admin`). | Test. |
| ID-FR-11 | Account deletion: after password confirmation, status becomes `deleting`, all sessions are revoked, and a job `account.purge` deletes the account row (cascade removes all tenant data) within `identity.purge.delay`. Audit rows keep `account_id` null-ed by cascade and retain only the action. | Journey J7. |
| ID-FR-12 | Data export (`account_exports`) produces a ZIP with `data.json` (all account data, schema `export_v1`, assembled from every module's `AccountExporter`, MOD-11), `landscape.ics`, and a `README.txt` explaining the format. Secrets and password hashes are excluded. Archives expire after `export.max_age_days`. | Test: ZIP contains no `password_hash`, no secret fields. |
| ID-FR-13 | Without SMTP, forgot-password is by admin-issued link (D-27); the request endpoint still returns 202 and does nothing. With SMTP, an email with a one-time link (TTL `auth.password_reset.ttl`) is sent; the email contains no account data other than the link. | Tests both configurations. |
| ID-FR-14 | Timezone must be a valid IANA name present in the embedded tz database; locale ∈ {`en`,`id`}. | Table tests. |

## 4. State machines

**Account status**

| From | Event | Guard | To | Failure/recovery |
|---|---|---|---|---|
| (none) | register / setup | policy allows | `active` | duplicate email → 409; nothing persisted |
| `active` | admin disables | not last admin | `disabled` | all sessions revoked; reversible by admin |
| `disabled` | admin enables | — | `active` | — |
| `active` | owner deletes | password ok, not last admin | `deleting` | job fails → retried; stays `deleting` and unusable; admin sees a stuck count |
| `deleting` | purge job | — | row deleted | — |

**Login attempt (per account)**

```
 idle ──bad credentials──▶ counting(n) ──n ≥ max within window──▶ locked(until)
  ▲                          │ good credentials                       │ time ≥ until
  └──────────────────────────┴────────────── reset counter ◀──────────┘
```
Locked + correct password → still `account_locked` (no oracle). Unknown email: counted per (email hash) in memory for rate limiting only, never persisted.

**Refresh family**: `active → revoked(reason)` (terminal). Tokens: `unused → used(rotated) | expired`.

## 5. Data flow — login

1. `kinevo-http` rate-limits by address (`ratelimit.auth_per_minute`).
2. System-context repository loads the account by normalised email (or none).
3. Constant-work verification: argon2id verify against hash or a dummy hash.
4. If lock active → 429 `account_locked`. If password wrong → increment counter (system tx), maybe lock, audit `auth.login_failed` (details: reason code only), return 401 `invalid_credentials`.
5. If TOTP enabled and no valid code supplied → 401 `mfa_required` (no counter change). Wrong code → counts as a failed attempt.
6. One transaction: reset counter, create family + first refresh token, audit `auth.login`, outbox `AccountLoggedIn`.
7. Response: access token in body; refresh token in cookie.

## 6. Validation

| Field | Kind | Rule |
|---|---|---|
| `email` | `email` | NFC, trimmed, lower-cased, ≤ 254 chars, one `@`, domain has a dot or is punycode; no display-name form |
| `password` | `secret_text` | see ID-FR-02; never trimmed (spaces allowed), never logged |
| `display_name` | `short_text` | 1–80, no control characters, NFC, trimmed |
| `timezone` | `iana_tz` | in tz database |
| `locale` | `enum` | `en`,`id` |
| `totp code` | `digits` | exactly 6 digits |
| `invite/reset tokens` | `opaque_token` | 43 chars base64url |

## 7. Events and jobs

Emits: `AccountRegistered`, `AccountLoggedIn`, `AccountDisabled`, `AccountDeletionRequested`, `PasswordChanged`. Consumes: none.
Jobs: `account.purge`, `account.export.build`, `identity.sessions.prune` (daily; deletes expired families/tokens), `identity.resets.prune`, `identity.exports.prune` (daily; sets `expired` and deletes the bytes of exports past `expires_at`, SM-21).

## 8. Abuse cases (tests required)

| ID | Case | Expected |
|---|---|---|
| SEC-AC-03 | Credential stuffing: 100 logins/min from one address | `rate_limited`; lockout per account; audit entries; no timing oracle |
| SEC-AC-04 | Refresh token replay from a stolen copy | family revoked, attacker and victim must re-login, audit `auth.refresh_reuse` |
| SEC-AC-05 | CSRF against `/auth/refresh` from another origin | 403 `csrf_check_failed` |
| SEC-AC-06 | Registration when closed | 403, no account row |
| SEC-AC-07 | TOTP code replay in the same step | rejected |
