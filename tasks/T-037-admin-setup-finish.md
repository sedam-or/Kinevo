---
id: T-037
title: Implement Finish setup as one provisioning transaction
milestone: M1
kind: feature
status: in_progress
depends_on: [T-036, T-025, T-019, T-014, T-033, T-034]
requirements: [ADM-FR-02, SET-01, SET-02, SET-03, SET-06, MOD-10, D-39, D-42]
branch: m1-t037-admin-setup-finish
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-037: Implement Finish setup as one provisioning transaction

## Goal

`POST /setup/session/{id}/finish` creates the first account, applies the template, switches, policies and marks the instance ready in one atomic transaction, then signs the user in.

## Context

> **Data flow** (`spec/modules/admin.md` §4): `BEGIN` (admin context: `kinevo_app`, `app.account_id` = new account id, `app.instance_admin = on`) → lock `instance_settings` row `FOR UPDATE`, if `setup_completed` → rollback 409 → apply module switches (validate graph) → insert instance `policy_versions` for template and wizard values → create the first account (hash computed before the transaction; `is_instance_admin = true`) → run every `AccountProvisioner` → create the first refresh family and token → insert account-scope policy rows from the template (first-account defaults) → set `instance_settings` (`setup_completed = true`, `setup_token_hash = NULL`, `template_id`, `base_url`, `default_locale`) → insert audit `setup.completed` → insert outbox `InstanceSetupCompleted` → `COMMIT`. On error: rollback, 503 or 422, wizard session remains.
> **SET-01**: with only email and password entered, setup completes using template defaults (journey J0). **SET-02**: any failure leaves `setup_completed = false` and no account, modules or policies. **SET-03**: Finish is idempotent per wizard session via `Idempotency-Key`; a second finish after success → 409 `setup_already_completed`.
> **SET-06**: `KINEVO_SETUP_EMAIL` and `KINEVO_SETUP_PASSWORD_FILE` may pre-create the first account non-interactively; it still audits and uses `KINEVO_SETUP_TEMPLATE` (default `personal`).
> **MOD-10**: provisioners run in dependency order inside the account-creation transaction; a failure rolls back everything.
> **Port**: `AccountCreation<Tx>` (T-025) creates the `accounts` row, runs provisioners and the first session; it neither begins nor commits.
> **Response**: 201 `{ account, access_token, expires_in }` plus the refresh cookie (T-024 helpers).

## Scope

**In:**
- `FinishSetup` use case: pre-transaction password hashing (T-021), then `Tx::begin_admin(new_id)`, the steps above, commit.
- `POST /setup/session/{id}/finish` with `Idempotency-Key` (T-019) and the cookie/access-token response.
- Non-interactive path `bootstrap_from_env(cfg)` for SET-06, reusing the same use case, called from startup (T-039).
- Fault-injection hook: a test-only `FailAt(step)` seam (cfg `test`) to prove rollback at every step.

**Out:**
- Sample data: M2.
- Env parsing: T-039.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/app/finish_setup.rs` | create | the use case |
| `crates/kinevo-mod-admin/src/http/setup.rs` | modify | add finish route |
| `crates/kinevo-mod-admin/tests/finish_setup.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub struct FinishInput { pub account: NewSetupAccount, pub session: WizardState }
pub struct NewSetupAccount { pub email: String, pub password: Secret<String>, pub display_name: String, pub timezone: String }
impl FinishSetup { pub async fn run(&self, input: FinishInput, client: Client) -> Result<FinishOutcome, AppError>; }
// POST /setup/session/{id}/finish  Idempotency-Key  → 201 { account, access_token, expires_in } + Set-Cookie
```

## Business rules

1. The first account is both `instance_admin` and `account_owner`; its locale defaults to the wizard locale, its timezone to the input (browser value, else UTC).
2. The setup token hash is cleared in the same transaction, so a second finish cannot succeed even with a fresh wizard session.
3. A retry with the same `Idempotency-Key` returns the stored response without creating a second account.
4. Any error before commit leaves `setup_completed = false` and no row from the list above.

## Security

- The password never leaves `Secret<String>`; the hash is computed outside the transaction.
- The audit row `setup.completed` and the outbox event carry ids only.
- The response sets the refresh cookie exactly as login does (T-024); the access token is returned in the body only.

## Implementation notes

- The `FOR UPDATE` lock on `instance_settings` serialises two simultaneous Finish calls; the loser sees `setup_completed = true` and returns 409.
- Pass the module set to the provisioner list unfiltered: provisioners run for every compiled module (MOD-10).
- The fault seam wraps each step in a closure list so the test iterates `0..n`.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a wizard session with defaults **When** Finish gets only email and password **Then** 201; the account is `instance_admin`; `setup_completed=true`; the token hash is null (journey J0) | `finish_setup::set_01_defaults_only` |
| AC-2 | **Given** a failure injected at each step **When** Finish runs **Then** no account, module row, policy row or flag change remains | `finish_setup::set_02_atomic_at_every_step` |
| AC-3 | **Given** a double submit with one `Idempotency-Key` **When** both arrive **Then** one account and identical responses | `finish_setup::set_03_idempotent_per_key` |
| AC-4 | **Given** a finished instance **When** Finish is called again **Then** 409 `setup_already_completed` | `finish_setup::set_03_second_finish_conflicts` |
| AC-5 | **Given** a failing fake provisioner **When** Finish runs **Then** rollback and 503/422 with the wizard session intact | `finish_setup::mod_10_provisioner_failure_rolls_back` |
| AC-6 | **Given** `KINEVO_SETUP_EMAIL` and a password file **When** `bootstrap_from_env` runs **Then** the instance is ready, audit written, and no token needed | `finish_setup::set_06_env_bootstrap` |
| AC-7 | **Given** template `student` **When** Finish completes **Then** its modules and policies are stored and the account holds the account-scope defaults | `finish_setup::template_applied_at_finish` |
| AC-8 | **Given** a weak password **When** Finish **Then** 422 `validation_failed` with `password_too_short`, nothing applied | `finish_setup::password_policy_enforced` |

## Tests

```
cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing   # DB tests environment-limited without Docker/PostgreSQL
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
