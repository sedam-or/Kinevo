---
id: T-026
title: Implement account profile, password change and session management endpoints
milestone: M1
kind: feature
status: in_progress
depends_on: [T-025, T-019]
requirements: [ID-FR-07, ID-FR-14, AUTH-04, API-05, API-14, API-60, API-70, VAL-02]
branch: m1-t026-identity-account-sessions
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-026: Implement account profile, password change and session management endpoints

## Goal

A signed-in user can read and update their profile with optimistic concurrency, change their password (revoking other sessions), list their sessions and revoke one. Expired sessions and reset tokens are pruned by jobs.

## Context

> **Endpoints** (`identity.md` §2): `GET /account` (`identity.account.read`) → 200 profile `id, email, display_name, locale, timezone, week_start, totp_enabled, is_instance_admin, version`. `PATCH /account` (If-Match, `identity.account.update`) → 200; errors `version_conflict` 409, `timezone_invalid` 422, validation 422. `POST /account/password` (`identity.account.update`) → 204; other families revoked; errors `current_password_incorrect` 422, `password_too_short` 422. `GET /account/sessions` (`identity.session.manage`) → list of families `id, device_label, created_at, last_used_at, current`. `DELETE /account/sessions/{id}` → 204; `not_found` 404.
> **API-05**: `PATCH` partial update: omitted = unchanged, `null` = clear a nullable field; non-nullable `null` → 422 `field_not_nullable`. **API-60**: `If-Match: "<version>"` required (428 `precondition_required`), mismatch → 409 `version_conflict` with `details.current_version` and `details.current` (T-019 helper). **API-70**: `Idempotency-Key` on `DELETE` and `POST`.
> **API-14**: business re-verification (current password) answers **422** `current_password_incorrect`, never 401.
> **ID-FR-07**: changing password or disabling an account revokes all refresh families immediately; access tokens die within their TTL. **AUTH-04**: password change requires the current password (or a valid reset token) and revokes other families (all but the caller's current one).
> **ID-FR-14**: timezone must be a valid IANA name in the embedded tz database; locale ∈ {`en`,`id`}.
> **Validation** (`identity.md` §6): `display_name` `short_text` 1–80; `timezone` `iana_tz`; `locale` enum; `week_start` int 1–7. New password follows ID-FR-02 (T-021).
> **Contexts**: these routes run in `Tx::begin(account)` — `accounts` has `tenant_isolation` on `id` and the family table on `account_id`.
> **Events/audit**: `PasswordChanged` event (ids only); audit `auth.password_changed`.
> **Jobs** (`identity.md` §7): `identity.sessions.prune` (daily; deletes expired/revoked families and tokens older than the retention horizon), `identity.resets.prune` (daily; deletes used/expired reset rows) — handlers register through `jobs()`; the recurring schedule is declared with `JobSchedule` (cadence is a platform tick, T-039) — in this task implement the handlers and declare them in the descriptor.
> **Account profile `is_instance_admin`**: informational only; authorisation derives from the loaded row (T-017).

## Scope

**In:**
- Handlers and `RouteSpec`s for the five endpoints (`GET /account`, `PATCH /account`, `POST /account/password`, `GET /account/sessions`, `DELETE /account/sessions/{id}`), each with the permission key above; `RateClass::Api`; `IdemMode::Optional` for POST/DELETE.
- `app::profile`: `get_profile`, `update_profile(patch, version)` (domain function `apply_patch(profile, patch) -> Result<Profile, Vec<(path, FieldCode)>>` is pure, CA-01), version increment in SQL (`UPDATE … WHERE id = $1 AND version = $2 RETURNING …`; zero rows → fetch current and return `version_conflict`).
- `app::password::change_password(account, current: Secret<String>, new: Secret<String>, current_family: FamilyId)`: verify the current password (`verify`, constant work); wrong → `current_password_incorrect` (its own registry code, HTTP 422); validate and hash the new password (same rules as registration, including common-list check); update `password_hash` and `version`; revoke all other families with reason `password_changed`; audit; emit `PasswordChanged`; all in one `Tx`.
- `app::sessions::list` (marks `current` where family id equals the access token's `sid`) and `revoke(id)` (own family only, RLS makes foreign ids invisible → `not_found`; revoking the current session is allowed and clears nothing else).
- Job handlers `identity.sessions.prune` and `identity.resets.prune` via `SystemTx` using `prune_expired` (T-022) and a reset-table equivalent; the retention horizon for revoked/expired families is 7 days (a technical constant documented here; if the implementer believes it should be a policy, record a Question instead of inventing a policy key).
- Descriptor update: add the endpoints' permissions are already present; add the two job kinds (already listed in T-020).

**Out:**
- TOTP endpoints: T-027. Account deletion/export: T-029. Admin account endpoints: T-028.
- Web UI: T-051.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-identity/src/domain/profile.rs` | create | `Profile`, `ProfilePatch`, `apply_patch` |
| `crates/kinevo-mod-identity/src/app/profile.rs` | create | |
| `crates/kinevo-mod-identity/src/app/password.rs` | create | |
| `crates/kinevo-mod-identity/src/app/sessions.rs` | create | |
| `crates/kinevo-mod-identity/src/app/jobs.rs` | create | prune handlers |
| `crates/kinevo-mod-identity/src/http/account.rs` | create | handlers |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | modify | profile queries |
| `crates/kinevo-mod-identity/src/lib.rs` | modify | routes, `jobs()` |
| `crates/kinevo-mod-identity/tests/account.rs` | create | |

## Interfaces

```rust
pub struct ProfilePatch { pub display_name: Option<String>, pub locale: Option<String>, pub timezone: Option<String>, pub week_start: Option<i64> }  // all non-nullable; JSON null → field_not_nullable
pub struct Profile { pub id: AccountId, pub email: String, pub display_name: String, pub locale: Locale, pub timezone: IanaTz, pub week_start: u8, pub totp_enabled: bool, pub is_instance_admin: bool, pub version: i64 }
```

## Business rules

1. `PATCH` with no changes still returns 200 and does **not** bump the version.
2. `version_conflict` returns the current profile in `details.current`.
3. `week_start` must be 1–7; `timezone` and `locale` are validated before the write; all field errors are returned together.
4. Password change: wrong current password → 422 `current_password_incorrect`; new password rules as ID-FR-02; the caller's own session stays valid; every other family is revoked.
5. `DELETE /account/sessions/{id}` of another account's family or a missing id → 404 `not_found` (SEC-07).
6. The sessions list excludes revoked families and orders by `last_used_at DESC, id`.
7. The email cannot be changed in 1.0 (no endpoint; documented in the Report if the owner asks).

## Security

- The current password is verified with the shared `Hasher` (constant work); it is never logged or placed in audit details.
- `PasswordChanged` carries ids only.
- The profile response contains no password hash, token or TOTP material.
- `DELETE /account/sessions/{id}` cannot be used to enumerate foreign sessions.

## Implementation notes

- `current` flag: compare the family id with `AuthCtx.session_id`.
- Use `ValidatedJson<ProfilePatch>` with a custom deserializer to distinguish absent from `null` (`Option<Option<T>>` pattern is not needed since no field is nullable: a `null` is a type error mapped to `field_not_nullable` in `validate`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** an account **When** `GET /account` **Then** the profile has exactly the documented keys and no secrets | `account::get_profile_shape` |
| AC-2 | **Given** version 3 **When** `PATCH` with `If-Match: "3"` and a new display name **Then** 200 with version 4; **When** repeated with `"3"` **Then** 409 with `details.current_version = 4` and `details.current` | `account::api_60_patch_optimistic_concurrency` |
| AC-3 | **Given** no `If-Match` **When** `PATCH` **Then** 428 `precondition_required` | `account::api_60_if_match_required` |
| AC-4 | **Given** bad timezone, bad locale and `week_start = 9` together **When** patched **Then** 422 with three errors; `{"display_name": null}` → `field_not_nullable` | `account::id_fr_14_patch_validation` |
| AC-5 | **Given** an empty patch **When** applied **Then** 200 and the version is unchanged | `account::empty_patch_does_not_bump_version` |
| AC-6 | **Given** three sessions **When** the password is changed from session A **Then** 204; sessions B and C are revoked, A still refreshes; `PasswordChanged` emitted; audit written | `account::auth_04_password_change_revokes_others` |
| AC-7 | **Given** a wrong current password **When** changing **Then** 422 `current_password_incorrect` (not 401) and no change | `account::api_14_wrong_current_password_is_422` |
| AC-8 | **Given** a new password on the common list **When** changing **Then** 422 with `password_too_common` | `account::password_rules_apply_on_change` |
| AC-9 | **Given** two sessions **When** listing **Then** exactly one has `current = true`; **When** the other is deleted **Then** 204 and it no longer refreshes; a foreign or random id → 404 | `account::sessions_list_and_revoke` |
| AC-10 | **Given** expired and fresh families and reset rows **When** the prune handlers run with the injected clock **Then** only expired rows beyond the horizon are deleted | `account::jobs_prune_expired_sessions_and_resets` |
| AC-11 | **Given** `POST /account/password` with the same `Idempotency-Key` twice **When** sent **Then** the password changes once and the second returns status-only replay | `account::password_change_is_idempotent` |

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
