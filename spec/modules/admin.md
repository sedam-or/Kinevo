# Module `admin`

Kind: core. Depends on: `identity`. Tables: `instance_settings`, `instance_modules`, `policy_versions`,
`secrets` (`06-domain-model.md` §3). Reads the platform tables (`audit_log`, `jobs`, `outbox_*`, `idempotency_keys`) only through `kinevo-db` APIs (D-41).
Implements the platform ports `PolicyReader`, `ModuleSwitches`, `InstanceState` and `SecretStore` (D-41). Prefix: `ADM`. Cross-spec: `03-module-system.md`, `04-policies.md`, `05-templates-and-setup.md`.

## 1. Permissions

| Key | `account_owner` | `instance_admin` |
|---|---|---|
| `admin.policies.account.read` / `.write` (account-scope keys, own account) | ✓ | ✓ |
| `admin.policies.instance.read` | ✓ | ✓ |
| `admin.policies.instance.write` | — | ✓ |
| `admin.modules.manage` | — | ✓ |
| `admin.audit.read` (instance) | — | ✓ |
| `admin.audit.read_own` (account's own entries) | ✓ | ✓ |
| `admin.jobs.read`, `admin.jobs.retry` | — | ✓ |
| `admin.secrets.manage` | — | ✓ |
| `admin.instance.read` | ✓ | ✓ |

## 2. Endpoints

| Method / path | Permission | Success | Errors |
|---|---|---|---|
| `GET /setup/status` | public (pre-setup only) | `{ setup_completed, templates:[{id,name_key,description_key}], locales }` | — |
| `POST /setup/session` `{ token }` | public | 201 `{ session_id, step: 1 }` | `setup_token_invalid` 422, `rate_limited` 429, `setup_already_completed` 409 |
| `GET /setup/session/{id}` | setup session | 200 current wizard state | `not_found` |
| `PUT /setup/session/{id}` `{ step, template_id?, locale?, base_url?, modules?, policies? }` | setup session | 200 state (validated; nothing applied yet) | `template_invalid`, `module_dependency_unmet`, `policy_value_invalid`, `policy_unknown` |
| `POST /setup/session/{id}/finish` `{ account:{email,password,display_name,timezone} }` + `Idempotency-Key` | setup session | 201 `{ account, access_token, expires_in }` + cookie | validation 422, `setup_already_completed` 409, `service_unavailable` 503 |
| `GET /instance` | `admin.instance.read` | `{ version, setup_completed, base_url, default_locale, template_id, registration_mode }` | — |
| `GET /modules` | any authenticated | `{ items:[{key, kind, enabled, nav[], settings_panels[]}] }` (MOD-01) | — |
| `PUT /admin/modules/{key}` `{ enabled, version }` | `admin.modules.manage` | 200 | `module_core_locked`, `module_dependency_unmet`, `module_required_by`, `version_conflict`, `not_found` |
| `GET /policies?scope=` | scope-dependent | 200 resolved list with metadata (POL-02) | — |
| `PUT /policies/{key}` | scope-dependent | 200 `{version_id, effective_from}` (POL-03) | see POL-03 |
| `GET /admin/audit` | `admin.audit.read` | page of entries; filters `action`, `from`, `to`, `account_id` | — |
| `GET /account/audit` | `admin.audit.read_own` | page of own entries | — |
| `GET /admin/jobs/summary` | `admin.jobs.read` | counts by `kind`/`state`, oldest queued age, dead outbox deliveries | — |
| `POST /admin/outbox/{event_id}/{subscriber}/retry` | `admin.jobs.retry` | 202 | `not_found`, `not_dead` 409 |
| `PUT /admin/secrets/{name}` `{ value }` | `admin.secrets.manage` | 204 | `secret_name_unknown` 422, validation |
| `GET /admin/secrets` | same | `[{ name, is_set, last4 }]` (never values) | — |
| `DELETE /admin/secrets/{name}` | same | 204 | `not_found` |

**Secret name registry** (D-40). Instance secrets (`account_id` null, managed by these endpoints): `smtp.password`, `ai.api_key`, `billing.gateway_key`, `billing.webhook_secret`. Account secrets (managed by their owning module through the `SecretStore` port in `kinevo-ports`, which this module implements): `identity.totp_seed` (identity), `ai.api_key` (ai, BYOK). Any other name → `secret_name_unknown`. `SecretStore` offers `put`, `get` (returns a redacting `Secret<String>`), `delete` and `mask` (`is_set`, `last4`) and encrypts as in ADM-FR-06.

## 3. Functional requirements

| ID | Requirement | Acceptance |
|---|---|---|
| ADM-FR-01 | Before `setup_completed`, every route except `/setup/*`, `/livez`, `/readyz` returns 409 `setup_required`. After completion, `/setup/*` returns 409 `setup_already_completed`. | Table test over all routes. |
| ADM-FR-02 | Setup obeys SET-01..SET-06 (`05-templates-and-setup.md`). | Journey J0. |
| ADM-FR-03 | Module switching obeys MOD-01..MOD-09. | Module tests. |
| ADM-FR-04 | Policies obey POL-01..POL-08; account-scope writes require the account's own context; instance-scope writes require `instance_admin`. | Permission matrix test. |
| ADM-FR-05 | `audit_log` receives an entry for: setup completed, module switched, policy changed, secret set/deleted (name only, never value), login success/failure, refresh reuse, account status changes, account deleted/exported, outbox retry, startup. `details` contains ids, enum values and counts only. | Test per action + PII scan test over `details`. |
| ADM-FR-06 | Secrets are stored as AEAD ciphertext under the master key `KINEVO_SECRET_KEY` (32 random bytes, base64) using the key id; API returns only `is_set` and `last4` (masked `••••` + last4, D-19). Rotating the master key is `kinevo admin rotate-secret-key --old --new`, which re-encrypts every row per scope (each account, then instance rows), skips rows already on the new `key_id` and is safe to rerun (D-47). | Tests: ciphertext differs per row (random nonce); rotation keeps values readable and a rerun after a mid-way failure completes. |
| ADM-FR-07 | The audit log is append-only for the application role; retention job `admin.audit.prune` deletes entries older than `audit.retention_days` through a SECURITY DEFINER function. | Test: `UPDATE`/`DELETE` as `kinevo_app` fail. |
| ADM-FR-08 | Idempotency keys older than `api.idempotency.ttl` are pruned by job `admin.idempotency.prune` (hourly). | Test. |
| ADM-FR-09 | `kinevo admin` CLI offers: `setup-token`, `reset-password <email>` (prints a one-time link), `list-accounts`, `rotate-secret-key`, `apply-template <id>`, `module <key> on|off`. Each CLI action writes an audit entry with `actor_id = null` and `details.via = "cli"`. | CLI tests. |

## 4. Data flow — Finish setup (transaction boundary)

```
BEGIN (provisioning transaction, admin context: kinevo_app, app.account_id = new account id, app.instance_admin = on; ADR-0011)
  lock instance_settings row FOR UPDATE; if setup_completed → ROLLBACK 409
  apply module switches (upsert instance_modules, validate graph)
  insert policy_versions (instance scope) for template + wizard values
  create first account (hash password; is_instance_admin = true)
  run every AccountProvisioner (MOD-10): default workspace, schedule_states row
  create first refresh family + token
  insert account-scope policy_versions from template (acct defaults)
  set instance_settings: setup_completed=true, setup_token_hash=NULL, template_id, base_url, default_locale
  insert audit_log(setup.completed) ; insert outbox(InstanceSetupCompleted)
COMMIT   → respond with the access token and set the refresh cookie
```
On any error: `ROLLBACK`; response 503/422; the wizard session remains; user retries (idempotent).

## 5. State machine — instance

| State | Meaning | Allowed routes |
|---|---|---|
| `uninitialised` (`setup_completed=false`) | fresh DB | `/setup/*`, `/livez`, `/readyz` |
| `ready` | normal | everything permitted |

There is no transition back; resetting an instance is a documented operation outside the product (drop database).

## 6. Validation

| Field | Kind | Rule |
|---|---|---|
| `base_url` | `https_url` | absolute `https://` URL (or `http://localhost`/loopback for dev), no path beyond `/`, no credentials; trailing slash removed |
| `module key` | `key` | `^[a-z][a-z0-9_]{1,23}$` and known |
| secret `value` | `secret_text` | 1–4096 chars, not trimmed, never echoed |

## 7. Events / jobs

Emits: `InstanceSetupCompleted`, `ModuleSwitched`, `PolicyChanged`, `SecretChanged` (name only). Jobs: `admin.audit.prune` (daily), `admin.idempotency.prune` (hourly), `jobs.prune` (daily, deletes finished jobs older than 7 days), `outbox.prune` (daily, delivered events older than 7 days), `outbox.dispatch` (continuous loop in worker).

## 8. Abuse cases

| ID | Case | Expected |
|---|---|---|
| SEC-AC-08 | Stranger finds a fresh public instance and calls `/setup/session` with guesses | 429 after 3 tries; no account created; audit `setup.token_failed` |
| SEC-AC-09 | `account_owner` calls `PUT /admin/modules/ai` | 403 `forbidden` |
| SEC-AC-10 | Admin reads `/admin/accounts` expecting note/task content | Response contains no content fields (contract test over the schema) |
| SEC-AC-11 | Secret value appears in logs/audit/OpenAPI examples | Scan test finds none |
