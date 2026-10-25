---
id: T-027
title: Implement optional TOTP enrolment, verification, recovery codes and the replay cache
milestone: M1
kind: feature
status: todo
depends_on: [T-026, T-023, T-008]
requirements: [ID-FR-08, SEC-AC-07, D-40, AUTH-05, ID-FR-04]
branch: m1-t027-identity-totp
budget_lines: 420
assignee: ""
---

# T-027: Implement optional TOTP enrolment, verification, recovery codes and the replay cache

## Goal

Users can turn on TOTP, sign in with a code or a single-use recovery code, and turn it off again. Seeds are stored encrypted through the `SecretStore` port, codes cannot be replayed within a step, and the instance policy can require TOTP.

## Context

> **ID-FR-08**: TOTP (RFC 6238, SHA-1, 6 digits, 30 s, ±1 step) is optional per account unless `auth.totp.required`. Seeds are stored as the account secret `identity.totp_seed` through the `SecretStore` port (AEAD, D-40). A used code cannot be reused in the same step (replay cache keyed by account + step). 10 single-use recovery codes are issued at enrolment, stored hashed.
> **AUTH §1**: TOTP RFC 6238 SHA-1, 6 digits, 30 s, ±1 step, 160-bit secret; seed stored as AEAD secret; replay cache `(account, step)` in memory for 90 s. Recovery codes: 10 codes, 10 chars from a 32-symbol unambiguous alphabet (≈ 50 bits each), stored as SHA-256 of the normalised code, single use. One-time tokens 256-bit random.
> **Endpoints** (`identity.md` §2): `POST /account/totp/enroll` (`identity.totp.manage`) → 200 `{ otpauth_uri, recovery_codes[10] }` (shown once; TOTP not yet active), error `totp_already_enabled` 409. `POST /account/totp/confirm` `{code}` → 204; `totp_enabled=true`; `totp_code_invalid` 422. `DELETE /account/totp` `{password, code}` → 204; errors `current_password_incorrect` 422, `totp_code_invalid` 422, `totp_required_by_policy` 409.
> **API-14**: authenticated re-verification failures are 422 (`totp_code_invalid`, `current_password_incorrect`), never 401. At **login** a wrong code is `invalid_mfa_code` 401 (T-023).
> **SEC-AC-07**: TOTP code replay in the same step → rejected. **D-40**: secrets are reached via `SecretStore` by `(account_id, name)`; registered name `identity.totp_seed`; no FK to `secrets`.
> **Schema**: `recovery_codes(id, account_id, code_hash bytea, used_at, created_at)`; `accounts.totp_enabled`. During enrolment the seed exists in the secret store but `totp_enabled` is false; re-enrolling before confirmation replaces the seed and recovery codes.
> **`auth.totp.required`** (bool, default false): if true, accounts must enrol at next login (T-023 already returns `mfa_enrol_required`); `DELETE /account/totp` then fails with `totp_required_by_policy`.
> **Port**: `SecretStore` (T-008; the real implementation arrives with T-032; tests use `FakeSecretStore`). `MfaVerifier` trait from T-023 is implemented here and wired by the app.
> **Stack**: `totp-rs` 6.0.0 (MIT) is in the stack map.

## Scope

**In:**
- `domain::totp`: pure `step_for(now, period=30) -> u64`, `accepts(seed, code, now, last_used_step_check)` wrapper around `totp-rs` verifying ±1 step; `RecoveryCode` newtype (10 chars from the alphabet `ABCDEFGHJKMNPQRSTVWXYZ23456789` extended to 32 symbols by the implementer without ambiguous characters `0 O 1 I L U`; list them in a constant and a test), `generate_recovery_codes(&dyn TokenGen) -> [RecoveryCode; 10]`, normalisation (upper-case, strip spaces and hyphens), `hash(code) -> [u8;32]`.
- `app::totp::TotpService`:
  - `enroll(tx, account) -> Enrolment { otpauth_uri: Secret<String>, recovery_codes: Vec<RecoveryCode> }`: refuses if `totp_enabled`; generates a 160-bit seed (20 bytes) from `TokenGen`; stores it via `SecretStore.put(Account(id), identity.totp_seed, …)` (base32 text); replaces existing unconfirmed recovery codes; the `otpauth://totp/Kinevo:<account label>?secret=…&issuer=Kinevo&algorithm=SHA1&digits=6&period=30` URI uses the account email as label.
  - `confirm(tx, account, code, now)`: verifies against the stored seed (and records the used step), sets `totp_enabled = true`, bumps `version`, writes audit? (no audit action exists for this; emit nothing).
  - `disable(tx, account, password, code, policy)`: verify password (422 `current_password_incorrect`), verify code or recovery code (422 `totp_code_invalid`), refuse when `auth.totp.required` (409 `totp_required_by_policy`); deletes the secret and all recovery codes; `totp_enabled = false`.
  - `TotpMfaVerifier` implementing `MfaVerifier` (T-023): accepts a TOTP code (checked with the replay cache) or a recovery code (consumed with `UPDATE … SET used_at = $now WHERE code_hash = $h AND used_at IS NULL RETURNING id`).
- `ReplayCache` (moka, 10 000 entries, 90 s, keyed `(account, step)`): `check_and_mark(account, step, now) -> bool`.
- Handlers/`RouteSpec`s for the three endpoints (`identity.totp.manage`, `RateClass::Api`, `store_response_body: false`).
- Login integration test in `tests/totp.rs` using the real `TotpMfaVerifier` with T-023's login service.

**Out:**
- Web enrolment UI (T-051); QR rendering is a web concern (the API returns the URI only).
- Admin-forced reset of a user's TOTP (not in 1.0).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/Cargo.toml` | modify | `totp-rs`, `data-encoding` (base32) if not pulled by `totp-rs` |
| `crates/kinevo-mod-identity/src/domain/totp.rs` | create | |
| `crates/kinevo-mod-identity/src/app/totp.rs` | create | service, verifier, replay cache |
| `crates/kinevo-mod-identity/src/infra/recovery.rs` | create | repository on `recovery_codes` |
| `crates/kinevo-mod-identity/src/http/totp.rs` | create | handlers |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | routes |
| `crates/kinevo-mod-identity/tests/totp.rs` | create | |

## Interfaces

```rust
pub struct Enrolment { pub otpauth_uri: Secret<String>, pub recovery_codes: Vec<RecoveryCode> }
pub struct ReplayCache; impl ReplayCache { pub fn check_and_mark(&self, a: AccountId, step: u64, now: Instant) -> bool /* true = first use */ }
pub enum MfaInput { Totp(Digits), Recovery(RecoveryCode) }
```

JSON: enrol response `{ "otpauth_uri": "otpauth://…", "recovery_codes": ["ABCDE-FGHJK", …] }` (codes shown with one hyphen in the middle for readability; normalisation removes it).

## Business rules

1. Window ±1 step around `now`; a code from step `s` is accepted at most once per account (second use → invalid), even if both uses fall in the tolerance window.
2. Enrolment is idempotent until confirmed (a second `enroll` replaces seed and codes); after confirmation → 409 `totp_already_enabled`.
3. Recovery codes are shown only in the enrol response; stored as SHA-256 of the normalised code; each works once; using one at login yields `mfa = true`.
4. Disabling requires **both** the current password and a valid TOTP or recovery code.
5. A wrong code at confirm → 422 `totp_code_invalid` and `totp_enabled` stays false.
6. With `auth.totp.required`, disable is refused with 409 `totp_required_by_policy`.
7. At login, the verifier never reveals whether a code was a replay or simply wrong (`invalid_mfa_code`).

## Security

- The seed is S3: it is stored only via `SecretStore` (AEAD), held in `Secret<…>` types, never logged, never returned after the enrol response (the URI embeds it once).
- Recovery codes are S3 in transit: the enrol response uses `Cache-Control: no-store` and `store_response_body = false`; they appear in no log or audit detail.
- Code comparison uses `totp-rs` `check` with constant-time comparison; recovery lookup is by hash in SQL.
- The replay cache is process-local (restart allows replay within one step; accepted, documented in `containers-and-caching.md`).

## Implementation notes

- Compute steps from the injected clock; pass `now` explicitly to `totp-rs` (`check_current` uses the system clock — use the `generate`/`check` variants taking a timestamp, or implement the HMAC step directly with `hmac`/`sha1` if the crate cannot take an injected time; record the choice).
- The `SecretStore` scope for the seed is `SecretScope::Account(id)`.
- `SecretStore` calls open their own short transaction inside the `admin` implementation (T-032). Call `put`/`delete` **before/after** the transaction that updates `accounts` and `recovery_codes`, never inside it. If the later step fails, an orphaned seed is harmless: the next `enroll` overwrites it and `disable` deletes it.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the RFC 6238 SHA-1 test vectors **When** codes are checked at their timestamps **Then** they verify, and ±1 step verifies while ±2 does not | `totp::tests::id_fr_08_rfc6238_vectors_and_window` |
| AC-2 | **Given** enrolment **When** `enroll` runs **Then** the URI has the documented parameters, 10 recovery codes of the right alphabet/length are returned, the seed exists in the fake `SecretStore`, `totp_enabled` is still false | `totp::id_fr_08_enroll_shape` |
| AC-3 | **Given** enrolment **When** confirming with a wrong then the right code **Then** 422 `totp_code_invalid`, then 204 and `totp_enabled = true` | `totp::id_fr_08_confirm_flow` |
| AC-4 | **Given** a confirmed account **When** the same valid code is used twice in the same step (login) **Then** the first succeeds and the second is `invalid_mfa_code` | `totp::sec_ac_07_replay_in_same_step_rejected` |
| AC-5 | **Given** a recovery code **When** used at login twice **Then** the first logs in with `mfa = true`, the second fails; lowercase and hyphenated input normalises | `totp::id_fr_08_recovery_code_single_use` |
| AC-6 | **Given** a confirmed account **When** `enroll` is called again **Then** 409 `totp_already_enabled` | `totp::totp_already_enabled` |
| AC-7 | **Given** wrong password or wrong code **When** disabling **Then** 422 `current_password_incorrect` / `totp_code_invalid`; with both right 204, the secret and recovery rows are gone | `totp::disable_requires_password_and_code` |
| AC-8 | **Given** `auth.totp.required = true` **When** disabling **Then** 409 `totp_required_by_policy` | `totp::totp_required_by_policy` |
| AC-9 | **Given** captured logs and audit after enrol/confirm/login **When** scanned for the seed, URI and recovery codes **Then** none appear | `totp::sec_ac_45_no_totp_material_in_logs` |
| AC-10 | **Given** the recovery alphabet constant **When** inspected **Then** it has 32 distinct symbols and none of `0 O 1 I L U` | `totp::tests::recovery_alphabet_is_unambiguous` |

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
