---
id: T-032
title: Implement the SecretStore port and the secrets endpoints
milestone: M1
kind: feature
status: review
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

### Summary

Added the admin `SecretStore` adapter, masked secret endpoints, audit and outbox writes, and a resumable master-key rotation function. Secret values remain inside redacting wrappers in application code and are never returned in list responses or written into audit details.

### Acceptance results

| Criterion | Test | Result |
|---|---|---|
| AC-1 | `secrets::adm_fr_06_nonce_differs_per_write` | pass |
| AC-2 | `secrets::adm_secret_put_and_list_masked` | pass |
| AC-3 | `secrets::secret_name_unknown_lists_allowed` | pass |
| AC-4 | `secrets::sec_ac_09_owner_forbidden` | pass |
| AC-5 | `secrets::sec_ac_11_no_secret_in_audit_or_logs` | pass |
| AC-6 | `secrets::aad_binds_name_and_scope` | pass |
| AC-7 | `secrets::adm_fr_06_rotation_keeps_values_readable` | pass; per-scope rollback and rerun follow D-47 |
| AC-8 | `secrets::delete_audits_name_only` | pass |

### Commands run

| Command | Result |
|---|---|
| `df -h /` | exit 0; `/dev/mapper/nvme0n1p3crypted 468G 402G 64G 87% /`; no cleanup was needed. |
| `eval "$(scripts/dev-postgres.sh env)"` | exit 0; reused the shared test PostgreSQL setup; the URL was not printed. |
| `PATH="$HOME/.cargo/bin:$PATH" NEXTEST_TEST_THREADS=2 cargo nextest run -p kinevo-mod-admin --features kinevo-db/testing --test secrets` | exit 0; 8 tests passed, 0 skipped. |
| `just check` (without the DB environment in a fresh shell) | exit 100; 181 passed, 8 DB tests failed with `Unavailable`, 118 not run, 1 skipped. The shared test URL was not inherited by that shell; this was an environment setup failure. |
| `pg_env="$(scripts/dev-postgres.sh env)"; eval "$pg_env"; just check` | exit 0; full gate completed, including formatting, Clippy, workspace tests, cargo-deny, spec, contrast and task-index gates. A full-suite run reported 307 passed, 1 existing slow timing test skipped. |
| `python3 scripts/spec_tool.py check` | exit 0; 0 findings in 60 spec files. |
| `python3 scripts/tasks_index.py --check` | exit 0; 0 problems in 132 tasks. |
| `python3 scripts/tasks_index.py` | exit 0; regenerated the index after the status change. |
| `git diff --check` and `git diff --cached --check` | exit 0. |
The full workspace run reported `307 tests run: 307 passed, 1 skipped`; the skipped test is the existing 200-sample timing comparison ignored for ordinary runs (`just test-slow`). Cargo-deny completed with existing duplicate-crate and unmatched-allowance warnings; its final gate output was `advisories ok, bans ok, licenses ok, sources ok`.

Environment-limited: none. Database tests used the one shared PostgreSQL server and per-test disposable databases under D-102; testcontainers were not used. The accepted run-wide disk decisions D-102–D-105 and D-109 were already recorded in `spec/decisions.md` and were followed: development debug info is disabled by default, no image was built or pulled, no volume or cache was deleted, and the milestone sweep is reserved for the M1 integration boundary.

### Files changed

Counts are measured against the T-032 start commit `bda9589`; `Cargo.lock` is generated and excluded under D-55. The per-file counts include this report and the regenerated task index.

| Path | Added | Removed |
|---|---:|---:|
| `Cargo.lock` (excluded) | 8 | 0 |
| `crates/kinevo-mod-admin/Cargo.toml` | 9 | 0 |
| `crates/kinevo-mod-admin/src/app/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/app/secrets.rs` | 116 | 0 |
| `crates/kinevo-mod-admin/src/domain/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/domain/secrets.rs` | 138 | 0 |
| `crates/kinevo-mod-admin/src/http/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/http/secrets.rs` | 141 | 0 |
| `crates/kinevo-mod-admin/src/infra/mod.rs` | 3 | 0 |
| `crates/kinevo-mod-admin/src/infra/secret_store.rs` | 532 | 0 |
| `crates/kinevo-mod-admin/src/lib.rs` | 4 | 3 |
| `crates/kinevo-mod-admin/tests/secrets.rs` | 750 | 0 |
| `spec/decisions.md` | 6 | 0 |
| `spec/index.md` | 7 | 1 |
| `spec/modules/admin.md` | 3 | 1 |
| `spec/standards/open-source-stack.md` | 1 | 1 |
| `tasks/T-032-admin-secret-store.md` | 96 | 3 |
| `tasks/index.md` | 2 | 2 |
| **Total excluding Cargo.lock** | **1,817** | **11** |

### Deviations

- AC-7 says a failure midway changes nothing, while D-47 requires one transaction per RLS scope. The implementation rolls back the failing scope, preserves earlier completed scopes, and lets a rerun skip rows already at the new key id. This is recorded as Q-1 for architect review; D-47 governs.
- The task's *Files* list omits the minimum manifest, module-export, shared-router, and decision/index wiring needed for the listed port, routes, and tests. Those small adjacent files are included and are detailed in the staged file list.
- The measured diff is 1,828 changed lines excluding `Cargo.lock`, versus the 390-line guidance. The required cross-scope rotation, HTTP surface, audit/outbox behavior, and eight acceptance tests account for the overage. D-55 makes the budget guidance non-blocking. `Cargo.lock` is generated and excluded.
- D-102 supersedes T-011's per-test testcontainer approach; the shared database policy was used for all database tests.

### Decisions made by implementer

The run-wide disk decisions D-102–D-105 and D-109 are existing accepted entries; no duplicate decision was added. T-032 decisions D-112–D-117 are pending owner review.

| ID | Choice and rejected alternative | Reason | Files | Reversal |
|---|---|---|---|---|
| D-112 | Encode registered secret names as underscore enum tokens; reject dotted free-form event values. | CA-11 accepts code-like enum payloads. | `spec/decisions.md`, `spec/index.md`, `spec/modules/admin.md`, admin outbox code | Supersede D-112 and update the event contract and tests. |
| D-113 | Keep the active secret key and a key-id-indexed fallback map. | Rows from completed scopes remain readable after a partial D-47 rotation. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-mod-admin/src/infra/secret_store.rs` | Supersede D-113 and migrate rotation/read configuration atomically. |
| D-114 | Attribute account-port writes to their account; require an active instance admin for direct instance writes; use the authenticated actor for HTTP writes. | The shared port has no actor parameter; changing it would force unrelated module migrations. | `spec/decisions.md`, `spec/index.md`, `spec/modules/admin.md`, admin store/service | Supersede D-114 and revise the port, callers, and audit contract together. |
| D-115 | Preserve `updated_at` during ciphertext-only rotation. | Rotation does not change the logical value; a wall-clock update would violate D-17. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-mod-admin/src/infra/secret_store.rs` | Supersede D-115 and add an injected clock to the rotation contract. |
| D-116 | Generate rotation nonces with the stack-approved `getrandom` CSPRNG. | The specified rotation signature has no `TokenGen`; deterministic randomness is unsafe. | `spec/decisions.md`, `spec/index.md`, `spec/standards/open-source-stack.md`, admin manifest/store | Supersede D-116 and change the public function contract to inject `TokenGen`. |
| D-117 | Treat CLI rotation as a process-authorized operation; nil UUID is only an RLS context, not an audit actor. | ADM-FR-09 requires CLI audit actor null; inventing an account actor would misstate the operation. | `spec/decisions.md`, `spec/index.md`, `crates/kinevo-mod-admin/src/infra/secret_store.rs` | Supersede D-117 and revise the CLI authorization/audit contract and rotation interface. |

### Spec issues noticed

Q-1 records the wording mismatch between T-032 AC-7's global atomicity and D-47's per-scope commit semantics. The implementation and test follow D-47 and verify that the failed scope rolls back and a rerun completes the remaining work. No other T-032 spec conflict was found.

### Not done / not verified

- CLI wiring and the CLI audit entry are explicitly assigned to T-040; this task verifies the rotation function directly.
- The full `just check` exit code and final per-file report counts are to be filled after regenerating the task index and running the final gate.
- Android, browser, and signing-key checks are not part of this task.

### Follow-up suggestions

Architect review of D-112–D-117 and Q-1; T-040 should add the specified CLI actor-null audit entry when it wires rotation.

## Questions

- Q-1 (implementer, 2026-10-29): T-032 AC-7 says a failure midway changes nothing, while D-47 says each account and the instance scope commit independently. What should the test assert after an error in a later scope?
  Resolved by implementer, pending architect review: D-47 governs. The failing scope rolls back; previously completed scopes may remain on the new key, and rerunning skips those rows and completes the remaining scopes.
