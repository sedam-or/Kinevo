---
id: T-025
title: Implement POST /auth/register, the account-creation port and provisioner execution
milestone: M1
kind: feature
status: todo
depends_on: [T-024, T-021, T-008, T-014]
requirements: [ID-FR-09, ID-FR-14, MOD-10, ADR-0011, SM-22, SEC-AC-06, FLW-02]
branch: m1-t025-identity-register
budget_lines: 420
assignee: ""
---

# T-025: Implement POST /auth/register, the account-creation port and provisioner execution

## Goal

New accounts can register according to the instance registration mode (closed, invite, open). Account creation, per-module provisioning and the first session happen in one transaction in the new account's context. The same creation logic is exposed as a port so the setup wizard (T-037) creates the first administrator the same way.

## Context

> **Endpoint**: `POST /auth/register` public → 201 `{ account, access_token, expires_in }` + refresh cookie; errors `registration_closed` 403, `invite_invalid` 422, `email_taken` 409, `password_too_short` / `password_too_common` / validation 422, `rate_limited` 429.
> **ID-FR-09**: Registration obeys `auth.registration.mode`: `closed` → only the setup wizard creates the first account; `invite` → valid unexpired unused invite token required; `open` → anyone (rate-limited). The first account of an instance is `instance_admin` (by the setup wizard). Table test across modes.
> **ADR-0011 provisioning**: Register and Finish setup run in **one** `kinevo_app` transaction in the *new* account's context: the application generates the UUIDv7 first, then `Tx::begin(new_id)` (or `begin_admin(new_id)` for Finish). The `accounts` row passes RLS because its `id` equals `app.account_id`. Duplicate emails are caught by the unique index and map to 409 `email_taken`. Rows owned by other modules are created by **account provisioners** (`AccountProvisioner<T>` in `kinevo-ports`, T-008).
> **MOD-10**: Account creation runs every compiled module's `AccountProvisioner` in module dependency order inside the same transaction as the `accounts` insert, whether or not a toggleable module is enabled. A provisioner failure rolls back the whole account creation. Test: a failing fake provisioner leaves no `accounts` row; a new account has exactly one default workspace and one schedule state (those two are verified in M2/M3; here with fakes).
> **FLW-02**: PROV-TX: check mode/invite → insert account → provisioners → family/token → audit → EV `AccountRegistered`; duplicate email ⇒ 409; nothing persisted.
> **SM-22 one-time token**: `issued → used` by atomic `UPDATE … WHERE used_at IS NULL AND expires_at > now` returning 1 row; reuse or expiry ⇒ `invite_invalid`. Tokens are hashes; 43-char base64url.
> **Validation**: `email`, `password` (T-021 rules), `display_name` `short_text` 1–80, `timezone` `iana_tz`, `locale` enum `en|id` (default instance default locale), `invite_token` `opaque_token` (required when mode is `invite`). Body is `{ email, password, display_name, timezone, locale?, invite_token? }`.
> **Defaults** for new accounts: `week_start` 1; `is_instance_admin` false; `status` active; `totp_enabled` false.
> **SEC-AC-06**: registration when closed → 403, no account row.
> **Events/Audit**: the outbox event `AccountRegistered` (ids only) records the registration; the first session creation writes the audit action `auth.login`. Do not add audit actions beyond T-012's list.

## Scope

**In:**
- Ports in `kinevo-ports::platform` (generic over the transaction type like T-008): `AccountCreation<T>` with `async fn create(&self, tx: &mut T, input: NewAccount) -> Result<CreatedAccount, AppError>` where `NewAccount { id: AccountId, email: Email, password_hash: PasswordHash-as-String, display_name, timezone, locale, is_instance_admin: bool, client: Client, device_label: Option<String> }` and `CreatedAccount { profile: AccountProfile, refresh_token: Secret<String>, access_claims: Claims-input }`; and `SessionIssuer<T>` (`issue(tx, account_id, mfa, client, device_label)`) if the implementer separates them. The password is hashed by the caller **before** the transaction (CPU-bound), so `NewAccount` carries the PHC string.
- `app::accounts::AccountCreator` implementing `AccountCreation<kinevo_db::Tx>`: inserts the `accounts` row, runs `provisioners` (an ordered `Vec<Arc<dyn AccountProvisioner<Tx>>>` injected; the order is given by `kinevo-app` from the dependency graph), creates the family + first token (T-022), emits `AccountRegistered`, writes audit `auth.login` (the first session). It does **not** begin or commit the transaction.
- `app::register::RegisterService::register(req, client, addr, now) -> Result<RegisterOutcome, AppError>`: resolve policy `auth.registration.mode` (instance scope, at now) → `closed` → `registration_closed`; validate fields (collect all errors, VAL-01); hash the password; generate the new `AccountId`; `Tx::begin(new_id)`; for `invite` mode atomically consume the invite (`UPDATE invites SET used_at = $now WHERE token_hash = $h AND used_at IS NULL AND expires_at > $now AND (email IS NULL OR email = $email) RETURNING id`; no row → `invite_invalid`); call `AccountCreator::create`; commit; respond 201.
- `infra::invites::consume(tx: &mut Tx, …)` (the consumption method requires a normal `Tx`; **creation** of invites, T-028, requires an admin `Tx`).
- Constraint map entry: `accounts_email_key` → `email_taken` (T-020).
- Handler and `RouteSpec`: `POST /auth/register`, `Public`, `RateClass::Auth`, `store_response_body: false`.
- Test provisioners `FailingProvisioner`, `RecordingProvisioner` for AC tests.

**Out:**
- Creating invites: T-028. Setup Finish: T-037 (uses `AccountCreation` with `Tx::begin_admin`).
- The real `workspaces`/`scheduler` provisioners (M2/M3).

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-ports/src/platform.rs` | modify | `AccountCreation`, `NewAccount`, `CreatedAccount` |
| `crates/kinevo-ports/src/fakes.rs` | modify | `FakeAccountCreation` |
| `crates/kinevo-mod-identity/src/app/accounts.rs` | create | `AccountCreator` |
| `crates/kinevo-mod-identity/src/app/register.rs` | create | `RegisterService` |
| `crates/kinevo-mod-identity/src/infra/invites.rs` | create | consume |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | modify | insert/profile |
| `crates/kinevo-mod-identity/src/http/register.rs` | create | handler |
| `crates/kinevo-mod-identity/tests/register.rs` | create | |

## Interfaces

```rust
#[async_trait] pub trait AccountCreation<T: Send>: Send + Sync {
    async fn create(&self, tx: &mut T, input: NewAccount) -> Result<CreatedAccount, AppError>;
}
pub struct RegisterRequest { pub email: String, pub password: Secret<String>, pub display_name: String, pub timezone: String, pub locale: Option<String>, pub invite_token: Option<String> }  // deny_unknown_fields
```

Registry codes used: `registration_closed` 403, `invite_invalid` 422, `email_taken` 409.

## Business rules

1. Mode `closed` → 403 `registration_closed` even for valid bodies; nothing is written.
2. Mode `invite` → the token must be valid, unexpired, unused, and (if the invite has an email) match; consumption and account creation are atomic: if account creation fails the invite stays unused.
3. Mode `open` → any valid body; a supplied `invite_token` is ignored.
4. Provisioners run in the injected order inside the transaction; the first failure rolls back everything (no account, no session, no invite consumption, no outbox event).
5. The new account's `is_instance_admin` is always false here.
6. Duplicate email (case-insensitive) → 409 `email_taken`, nothing persisted.
7. `locale` defaults to `InstanceState.default_locale`; `timezone` must be a valid IANA name (`timezone_invalid` otherwise).
8. All field errors are returned together (422 `validation_failed`).

## Security

- Registration on a closed instance reveals nothing but `registration_closed`.
- On open instances the `email_taken` conflict is the accepted, rate-limited enumeration trade-off (SEC-07).
- Passwords are hashed before the transaction; the transaction holds no password plaintext.
- Invite tokens are 256-bit random, stored as SHA-256; compared by hash in SQL.
- The tenant context for the transaction is the freshly generated id, never client input (SEC-05).

## Implementation notes

- The ordering of provisioners is data supplied by the composition root; this task only runs them in the given order and tests that order is preserved.
- Use `kinevo_ports::module::run_provisioners` (T-008) rather than re-implementing.
- Emit the event with `outbox::emit` using the `SubscriberRegistry` from the app.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** mode `open` **When** registering with valid data **Then** 201 with profile, access token, cookie; account, family and token rows exist; `AccountRegistered` in the outbox; two provisioner fakes ran in order | `register::id_fr_09_open_mode_success` |
| AC-2 | **Given** mode `closed` **When** registering **Then** 403 `registration_closed` and zero `accounts` rows | `register::sec_ac_06_closed_registration` |
| AC-3 | **Given** mode `invite` with valid, expired, used, wrong-email and missing tokens **When** registering **Then** success for the valid one and `invite_invalid` (422) for the rest; the valid invite is now used | `register::id_fr_09_invite_mode_table` |
| AC-4 | **Given** a failing provisioner **When** registering **Then** error response, no account/family/token row, invite unconsumed, no outbox event | `register::mod_10_failing_provisioner_rolls_back` |
| AC-5 | **Given** two registrations with emails differing in case **When** the second runs **Then** 409 `email_taken` | `register::email_taken_case_insensitive` |
| AC-6 | **Given** an invalid email, short password and bad timezone together **When** registering **Then** 422 with three errors at once | `register::val_01_all_errors_returned` |
| AC-7 | **Given** 20 concurrent registrations with the same email **When** run **Then** exactly one 201, the rest 409 | `register::tst_20_concurrent_same_email` |
| AC-8 | **Given** an invite used concurrently by two registrations **When** both run **Then** exactly one succeeds (SM-22 atomicity) | `register::sm_22_invite_single_use_under_race` |
| AC-9 | **Given** the new account **When** the session is created **Then** the family belongs to the new account id and RLS context equals that id (verified by running the registration with a spy asserting `Tx::begin(new_id)`) | `register::adr_0011_provisioning_in_new_account_context` |

## Tests

```
cargo nextest run -p kinevo-ports -p kinevo-mod-identity --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
