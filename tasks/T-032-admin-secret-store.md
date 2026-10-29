---
id: T-032
title: Implement the SecretStore port and the secrets endpoints
milestone: M1
kind: feature
status: in_progress
depends_on: [T-031, T-004, T-008, T-017, T-019]
requirements: [ADM-FR-05, ADM-FR-06, D-19, D-40, D-46, D-47, SEC-03, SEC-AC-11]
branch: m1-t032-admin-secret-store
budget_lines: 390
assignee: "OpenAI Codex"
---

# T-032: Implement the SecretStore port and the secrets endpoints

## Goal

Instance and account secrets are stored as AEAD ciphertext and exposed only as `is_set` and `last4`. An admin can set, list and delete instance secrets over HTTP, and other modules reach secrets only through the `SecretStore` port.

## Context

> **ADM-FR-06** (`spec/modules/admin.md`): Secrets are AEAD ciphertext under the master key `KINEVO_SECRET_KEY` using the key id; the API returns only `is_set` and `last4` (masked `••••` + last4, D-19). Ciphertext differs per row (random nonce). Rotation is `kinevo admin rotate-secret-key --old --new`, re-encrypting all rows in one transaction.
> **Endpoints** (`admin.md` §2): `PUT /admin/secrets/{name}` `{ value }` → 204 (`secret_name_unknown` 422, validation); `GET /admin/secrets` → `[{ name, is_set, last4 }]`; `DELETE /admin/secrets/{name}` → 204 or `not_found`. All need `admin.secrets.manage`.
> **Registry** (D-40): instance names `smtp.password`, `ai.api_key`, `billing.gateway_key`, `billing.webhook_secret`; account names `identity.totp_seed`, `ai.api_key`. Any other name → `secret_name_unknown` with `details.allowed`.
> **Validation**: secret `value` kind `secret_text`, 1–4096 chars, not trimmed, never echoed.
> **ADM-FR-05**: audit `secret.set` / `secret.deleted` carry the name only, never the value.
> **Crypto** (T-004): subkey `KeyLabel::Secrets`, `seal`/`open` with AAD `"secret:" + name + ":" + account_id_or_instance`. The `secrets` table has `ciphertext`, `nonce`, `key_id`, `last4` (T-031).

## Scope

**In:**
- `AdminSecretStore` implementing `SecretStore` over `Tx`/admin `Tx` (account rows use the caller's account context, instance rows use `begin_admin`).
- Secret name registry as a typed enum with `scope()` (instance/account) and a `parse` returning `secret_name_unknown`.
- The three endpoints, audit entries and the `SecretChanged` outbox event (name only).
- `rotate_master_key(tx, old, new)` function used by the CLI (T-040); one transaction, all rows re-sealed, `key_id` bumped.

**Out:**
- The CLI wiring of rotation: T-040.
- Consumers of secrets (SMTP, TOTP, AI): their own tasks.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/domain/secrets.rs` | create | `SecretNameKind`, scope rules |
| `crates/kinevo-mod-admin/src/infra/secret_store.rs` | create | `AdminSecretStore`, rotation |
| `crates/kinevo-mod-admin/src/http/secrets.rs` | create | 3 handlers + route specs |
| `crates/kinevo-mod-admin/src/app/secrets.rs` | create | use cases with audit and outbox |
| `crates/kinevo-mod-admin/src/lib.rs` | modify | mount routes |
| `crates/kinevo-mod-admin/tests/secrets.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub struct AdminSecretStore { db: Db, key: Secret<[u8; 32]>, key_id: u16, ids: Arc<dyn IdGen>, tokens: Arc<dyn TokenGen> }
#[async_trait] impl SecretStore for AdminSecretStore { /* put, get, delete, mask per T-008 */ }
pub async fn rotate_master_key(db: &Db, old: &MasterKey, new: &MasterKey, new_key_id: u16) -> Result<u64, AppError>;
// GET /admin/secrets  → 200 [{ "name": "smtp.password", "is_set": true, "last4": "••••ab12" }]
```

## Business rules

1. A value is stored only after the name is validated against the registry and the scope matches (an account-only name cannot be written at instance scope).
2. `mask` returns `is_set = false` and no `last4` when no row exists.
3. Writing the same name twice replaces the ciphertext and nonce (new random nonce each time).
4. Rotation re-encrypts every row; it is resumable, because rows whose `key_id` already equals the new id are skipped, so a rerun finishes the job (a deliberate refinement of ADM-FR-06's single transaction: one transaction cannot span RLS contexts; recorded as D-47).

## Security

- Plaintext exists only inside a `Secret<String>`; no `Debug`, no log line, no audit detail contains it (SEC-AC-11 scan test over audit rows and a captured log).
- AAD binds each ciphertext to its name and scope; a ciphertext moved to another row fails to open.
- Rotation runs per scope: account ids are listed once through a `SystemTx`, each account's rows are re-sealed in its own `Tx::begin(account)`, and instance rows in `Tx::begin_admin`. A failure in any scope aborts that scope's transaction; the function reports the first error and the rerun skips rows already carrying the new `key_id`.

## Implementation notes

- Cache nothing: secrets are read on use. `last4` is stored at write time so listing needs no decryption.
- Use `X-Kinevo-Client` and `Idempotency-Key` handling from T-019 on the two write routes (`IdemMode::Optional`).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a secret **When** stored twice with the same value **Then** the two rows have different nonces and ciphertexts and both decrypt | `secrets::adm_fr_06_nonce_differs_per_write` |
| AC-2 | **Given** `PUT /admin/secrets/smtp.password` **When** an admin sends a value **Then** 204; `GET /admin/secrets` shows `is_set` and `last4` only | `secrets::adm_secret_put_and_list_masked` |
| AC-3 | **Given** an unknown name **When** put **Then** 422 `secret_name_unknown` with `details.allowed` | `secrets::secret_name_unknown_lists_allowed` |
| AC-4 | **Given** an `account_owner` **When** calling any secrets route **Then** 403 `forbidden` | `secrets::sec_ac_09_owner_forbidden` |
| AC-5 | **Given** audit rows and a captured log after puts **When** scanned for the value **Then** none found | `secrets::sec_ac_11_no_secret_in_audit_or_logs` |
| AC-6 | **Given** a ciphertext copied into another name's row **When** opened **Then** it fails | `secrets::aad_binds_name_and_scope` |
| AC-7 | **Given** two instance and two account secrets **When** rotating to a new key **Then** all stay readable and carry the new `key_id`; a failure midway changes nothing | `secrets::adm_fr_06_rotation_keeps_values_readable` |
| AC-8 | **Given** a missing secret **When** `DELETE` **Then** 404 `not_found`; deleting an existing one writes `secret.deleted` with the name only | `secrets::delete_audits_name_only` |

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
