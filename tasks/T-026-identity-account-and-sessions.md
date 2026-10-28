---
id: T-026
title: Implement account profile, password change and session management endpoints
milestone: M1
kind: feature
status: review
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

- [x] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [x] `just check` passes locally.
- [x] Listed files changed; minimal wiring outside the list and the line-budget overage are recorded as deviations under D-55/D-56.
- [x] Report below is filled in.

## Report

### Summary

Implemented the account profile read/update endpoints, optimistic concurrency, password change with session revocation, cursor-paginated session management, and the two retention jobs. All 11 named acceptance tests pass; the full `just check` gate passes.

### Acceptance results

| Criterion | Test | Result |
|---|---|---|
| AC-1 | `account::get_profile_shape` | pass |
| AC-2 | `account::api_60_patch_optimistic_concurrency` | pass |
| AC-3 | `account::api_60_if_match_required` | pass |
| AC-4 | `account::id_fr_14_patch_validation` | pass |
| AC-5 | `account::empty_patch_does_not_bump_version` | pass |
| AC-6 | `account::auth_04_password_change_revokes_others` | pass |
| AC-7 | `account::api_14_wrong_current_password_is_422` | pass |
| AC-8 | `account::password_rules_apply_on_change` | pass |
| AC-9 | `account::sessions_list_and_revoke` | pass |
| AC-10 | `account::jobs_prune_expired_sessions_and_resets` | pass |
| AC-11 | `account::password_change_is_idempotent` | pass |

### Commands run

- `df -h / && eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=4 cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing --test account --no-fail-fast` — exit 0; 11 passed, 0 skipped. An earlier run exposed a missing synthetic `ConnectInfo` in the HTTP test helper (10/11 passed); after adding it, all tests passed. A retention implementation prototype also failed AC-10 (10/11); it was replaced with scoped deletes under the existing `system_auth` policy and then passed.
- `cargo fmt --all` — exit 0.
- `df -h / && eval "$(scripts/dev-postgres.sh env)" && NEXTEST_TEST_THREADS=4 just check` — final exit 0: 248 passed, 1 skipped; `cargo deny check` exit 0; `scripts/spec_tool.py check` reported 0 findings in 60 files; contrast reported 0 failures; `scripts/tasks_index.py --check` reported 0 problems in 132 tasks. Earlier runs stopped on rustfmt and Clippy findings introduced during implementation; both were fixed before the final run. Existing warnings: `clippy.toml` names unreachable `rand::thread_rng`, and cargo-deny reports duplicate lock entries and unmatched license allowances.
- `python3 scripts/tasks_index.py` — exit 0; 0 problems in 132 tasks.
- `just spec` — exit 0; spec 0 findings in 60 files, contrast 0 failures, task index 0 problems in 132 tasks.

### Files changed

The implementation adds 1,807 lines and removes 4 across 16 files (including 785 acceptance-test lines), about 1,407 lines over the 400-line guidance budget; D-55 says this is not a blocker.

| Path | Added | Removed |
|---|---:|---:|
| `crates/kinevo-db/src/jobs.rs` | 8 | 0 |
| `crates/kinevo-mod-identity/migrations/20261101000600_identity_retention.sql` | 5 | 0 |
| `crates/kinevo-mod-identity/src/app/jobs.rs` | 65 | 0 |
| `crates/kinevo-mod-identity/src/app/mod.rs` | 4 | 0 |
| `crates/kinevo-mod-identity/src/app/password.rs` | 144 | 0 |
| `crates/kinevo-mod-identity/src/app/profile.rs` | 74 | 0 |
| `crates/kinevo-mod-identity/src/app/sessions.rs` | 76 | 0 |
| `crates/kinevo-mod-identity/src/domain/mod.rs` | 1 | 0 |
| `crates/kinevo-mod-identity/src/domain/profile.rs` | 96 | 0 |
| `crates/kinevo-mod-identity/src/http/account.rs` | 385 | 0 |
| `crates/kinevo-mod-identity/src/http/mod.rs` | 1 | 0 |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | 108 | 1 |
| `crates/kinevo-mod-identity/src/infra/mod.rs` | 1 | 0 |
| `crates/kinevo-mod-identity/src/infra/retention.rs` | 41 | 0 |
| `crates/kinevo-mod-identity/src/lib.rs` | 13 | 3 |
| `crates/kinevo-mod-identity/tests/account.rs` | 785 | 0 |
| `tasks/index.md` | 2 | 2 |
| `tasks/T-026-identity-account-and-sessions.md` | 81 | 7 |

### Deviations

- The identity task and module summary call session management a “list”, while API-30 requires cursor pagination and `{items, next_cursor}`. Followed API-30 and added the permitted `limit`, `cursor`, and `sort` query handling.
- Added a migration and narrow `DELETE` grants for `refresh_token_families` and `password_resets` to let `SystemTx` run the required jobs through the existing `system_auth` row policy. No new RLS policy or bypass role was added.
- Added a static `JobKind` constructor because `JobHandler::kind` is infallible while `JobKind::new` validates and returns a `Result`; the constructor is used only with module-owned literals.
- The task does not name the password-change JSON fields; used `current_password` and `new_password`.
- Small wiring outside the Files table: `kinevo-db/src/jobs.rs`, identity migration `20261101000600_identity_retention.sql`, `infra/mod.rs`, and new `infra/retention.rs`.

### Spec issues noticed

- API-30’s pagination envelope is more specific than the endpoint’s “list” wording.
- ADR-0011 and `spec/06-domain-model.md` list the system role’s identity-table grants without `DELETE`. This task needs those deletions from `SystemTx`; the grants are limited to the two required tables and rows remain constrained by `system_auth`.

### Not done / not verified

- No environment-limited checks. The focused database acceptance suite and full workspace tests used the existing shared PostgreSQL server via `scripts/dev-postgres.sh env`.
- No separate live-server/client E2E run; verification used the task’s `TestApp` integration tests and `just check`.

### Follow-up suggestions

- Architect review of Q-1 and Q-2 below, especially the `SystemTx` delete grants against ADR-0011’s grant matrix.

## Questions

- Q-1 (implementer, 2026-10-29): The task and identity endpoint table say the sessions endpoint returns a list, but API-30 requires cursor pagination. **Resolved by implementer, pending architect review:** followed API-30 with `{items, next_cursor}` and `limit`, `cursor`, and `sort` parameters. Options: (a) keep API-30 pagination; (b) revise the common list contract. Recommendation: (a), because it follows the accepted API rule.
- Q-2 (implementer, 2026-10-29): ADR-0011 and `spec/06-domain-model.md` omit `DELETE` from `kinevo_system` grants, while AC-10 requires deleting expired identity rows through `SystemTx`. **Resolved by implementer, pending architect review:** grant `DELETE` only on `refresh_token_families` and `password_resets`; the existing `system_auth` RLS policy still scopes the rows, and no extra policy or `BYPASSRLS` is used. Options: (a) keep these two grants; (b) add a separately scoped retention mechanism and amend the accepted security policy. Recommendation: (a) for the smallest change that satisfies the task while retaining forced RLS.
