---
id: T-025
title: Implement POST /auth/register, the account-creation port and provisioner execution
milestone: M1
kind: feature
status: review
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

### Summary

Implemented open, invite-only, and closed registration, including the public `POST /auth/register` route, transactional account creation, ordered provisioners, first refresh family/token, audit entry, and `AccountRegistered` outbox event. Registration validates and hashes the password before opening the new-account transaction; the named acceptance tests cover rollback, invite races, duplicate email, validation, and tenant context.

### Acceptance results

| AC | Named test | Result |
|---|---|---|
| AC-1 | `register::id_fr_09_open_mode_success` | pass |
| AC-2 | `register::sec_ac_06_closed_registration` | pass |
| AC-3 | `register::id_fr_09_invite_mode_table` | pass |
| AC-4 | `register::mod_10_failing_provisioner_rolls_back` | pass |
| AC-5 | `register::email_taken_case_insensitive` | pass |
| AC-6 | `register::val_01_all_errors_returned` | pass |
| AC-7 | `register::tst_20_concurrent_same_email` | pass |
| AC-8 | `register::sm_22_invite_single_use_under_race` | pass |
| AC-9 | `register::adr_0011_provisioning_in_new_account_context` | pass |

### Commands run

- `df -h /` before each build/test run: 84 GB available; no prune or resource removal was needed.
- Test-first `cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing register::`: exit 101 before implementation because the registration modules and router did not exist yet; the compiler reported unresolved registration imports. The initial named-test run then exposed the invalid-timezone fixture using the valid `UTC` name; it was corrected to `UTC+7`.
- `cargo fmt --all`: exit 0.
- `cargo nextest run -p kinevo-mod-identity --features kinevo-db/testing --test register`: final focused run passed all 9 acceptance tests.
- `cargo nextest run -p kinevo-ports -p kinevo-mod-identity --features kinevo-db/testing` with the default test parallelism: exit 100; 46 tests ran (42 passed, 4 failed, 1 skipped) and four DB test fixtures reported `disposable PostgreSQL test database should start: Unavailable` while starting in parallel.
- `export PATH="$HOME/.cargo/bin:$PATH" NEXTEST_TEST_THREADS=4; eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-ports -p kinevo-mod-identity --features kinevo-db/testing`: exit 0; 67 tests passed, 1 skipped. The shared PostgreSQL server reported `max_connections = 100`; limiting Nextest to four concurrent tests avoided the DB-startup failures without reducing concurrency inside the acceptance cases.
- Final `just check`, with `PATH` set, `NEXTEST_TEST_THREADS=4`, and `KINEVO_TEST_DATABASE_URL` from `scripts/dev-postgres.sh env`: exit 0. Output: 237 tests passed, 1 skipped; `cargo deny check` ended `advisories ok, bans ok, licenses ok, sources ok`; `scripts/spec_tool.py check` reported `0 finding(s) in 60 spec files`; contrast reported `failures: 0`; `scripts/tasks_index.py --check` reported `0 problem(s) in 132 task(s)`.
- Intermediate `just check` attempts caught, then fixed, a formatting diff, `clippy::needless_pass_by_value` in `RegistrationPolicy::from_snapshot`, and two `clippy::panic` findings in race-test error handling. The final gate above is after those fixes.

### Files changed

The implementation source/test/spec diff is 1,554 changed lines. This table excludes generated `Cargo.lock` and workflow bookkeeping (`tasks/T-025-identity-register-and-account-creation.md`, `tasks/index.md`). The 420-line task budget is exceeded; D-55 makes it guidance and this is recorded under Deviations.

| Path | Lines added / removed |
|---|---:|
| `crates/kinevo-mod-identity/src/app/accounts.rs` | +148 / -0 |
| `crates/kinevo-mod-identity/src/app/login.rs` | +1 / -14 |
| `crates/kinevo-mod-identity/src/app/mod.rs` | +2 / -0 |
| `crates/kinevo-mod-identity/src/app/refresh.rs` | +2 / -7 |
| `crates/kinevo-mod-identity/src/app/register.rs` | +280 / -0 |
| `crates/kinevo-mod-identity/src/http/mod.rs` | +1 / -0 |
| `crates/kinevo-mod-identity/src/http/register.rs` | +120 / -0 |
| `crates/kinevo-mod-identity/src/infra/accounts.rs` | +54 / -2 |
| `crates/kinevo-mod-identity/src/infra/invites.rs` | +32 / -0 |
| `crates/kinevo-mod-identity/src/infra/mod.rs` | +1 / -0 |
| `crates/kinevo-mod-identity/src/lib.rs` | +7 / -0 |
| `crates/kinevo-mod-identity/tests/register.rs` | +700 / -0 |
| `crates/kinevo-ports/Cargo.toml` | +1 / -0 |
| `crates/kinevo-ports/src/fakes.rs` | +61 / -4 |
| `crates/kinevo-ports/src/platform.rs` | +112 / -2 |
| `spec/decisions.md` | +1 / -0 |
| `spec/index.md` | +1 / -1 |

The task file contains this report and its `status: review` transition; `tasks/index.md` was regenerated by `scripts/tasks_index.py`. `Cargo.lock` has one generated line added for the approved port dependency.

### Deviations

- The 1,554 changed-line implementation diff exceeds `budget_lines: 420`. The nine acceptance tests need a PostgreSQL-backed HTTP/use-case fixture and race scenarios; no acceptance test was weakened or removed. `Cargo.lock` is generated and excluded from the budget under D-55.
- Added small module/router wiring and moved shared account/client DTOs into `kinevo-ports::platform`, with compatibility re-exports from the existing identity app paths. D-106 records this choice. The `utoipa` dependency was already approved by `spec/standards/open-source-stack.md`.
- The D-102 shared-PostgreSQL rule supersedes T-011's earlier testcontainers instruction. Every DB test uses the shared server and its own disposable database. The first unbounded package run hit four database-startup failures; the final package suite and full gate used `NEXTEST_TEST_THREADS=4`. The server remained the existing `kinevo-dev-pg`; no image was built or pulled, and no containers/images/volumes were pruned or deleted.
- Run-wide disk and cache rules D-103 through D-105 are documented in the earlier run-wide commit; no `cargo clean` or cache sweep was run in this task. The sweep is reserved for the M1 milestone boundary under D-104.

### Spec issues noticed

None affecting T-025.

### Not done / not verified

- No browser, Android emulator, or external-provider E2E was run; T-025's endpoint contract was exercised through the in-process Axum router and the task does not require those environments.
- No production database or deployment was used. All database acceptance tests used disposable per-test databases on the shared local PostgreSQL server.

### Follow-up suggestions

None.

### Decisions made by implementer

| ID | Choice and alternatives | Files and reversal |
|---|---|---|
| D-106 | Keep `AccountProfile` and the first-session `Client` transport class in `kinevo-ports::platform`, with re-exports from the existing identity app modules. This lets the account-creation port remain independent of identity application types while preserving current call sites. Duplicating the DTOs or making `kinevo-ports` depend on the identity crate were rejected. Pending owner review. | `crates/kinevo-ports/src/platform.rs`, `crates/kinevo-ports/src/fakes.rs`, `crates/kinevo-mod-identity/src/app/login.rs`, `crates/kinevo-mod-identity/src/app/refresh.rs`, `spec/decisions.md`, `spec/index.md`. Reverse by moving the shared types to another approved layer, updating port users, and superseding D-106. |

## Questions

_None yet._
