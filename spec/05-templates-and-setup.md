# 05 — Templates and the 3-step setup (D-10)

Depends on: `03-module-system.md`, `04-policies.md`. Requirement prefix: `TPL` (templates) and `SET` (setup).
Every distribution channel ends in this wizard (`14-distribution.md`).

## 1. Template

A **template** is a data file (no code) that selects modules, policy presets, terminology overrides and sample
content. Templates ship in the binary (`templates/*.toml`, embedded) and an admin may import additional ones (validated against the same schema; unknown keys rejected).

| Field | Rule |
|---|---|
| `id` | `[a-z][a-z0-9-]{1,31}`, unique |
| `name_key`, `description_key` | message keys (en + id required) |
| `modules` | map `module key → bool`; must satisfy dependency graph (MOD-03); core modules must be absent or `true` |
| `policies` | map `policy key → value`; each validated against the registry (POL-03); only `instance`-scope keys at instance setup, `account`-scope keys become the first account's defaults |
| `terms` | map `term key → message key`; only keys in the **terminology registry** (below) |
| `sample` | optional: id of a sample dataset (`student`, `professional`, `empty`) created for the **first account** only if the user ticks "add sample data" |

**Terminology registry** (overridable labels; never changes logic): `term.goal`, `term.milestone`, `term.task`, `term.workspace`, `term.hard_landscape`, `term.focus_session`. Defaults are the English/Indonesian catalogue entries. Example: template `student` renames `term.hard_landscape` to "Class & fixed hours".

### Shipped templates (1.0)

| Template | Modules on | Notable presets | Audience |
|---|---|---|---|
| `personal` (default) | core + `sync`, `knowledge` | registration `closed`, defaults from `04-policies.md` | Self-hoster |
| `student` | `personal` + `canvas` | terms: Class & fixed hours; `scheduler.day_start` 07:00; `scheduler.day_end` 22:00 | Students `[ASSUMED]` |
| `professional` | `personal` + `ai`(off until provider set) | terms: Commitments; `scheduler.anchor.earliest_time` 08:00 | Professionals `[ASSUMED]` |
| `cloud` | core + `sync`, `knowledge`, `canvas`, `ai`, `billing` (off until OWNER-01 set) | registration `open` | Kinevo Cloud operator |

Template content is provisional (`[ASSUMED]`) until the validation exercise.

## 2. Requirements

| ID | Requirement | Acceptance |
|---|---|---|
| TPL-01 | A template file that fails schema validation is rejected with a precise error naming the field path (`template_invalid`, `details.path`). | Table test with bad fixtures. |
| TPL-02 | Templates cannot contain code, URLs to fetch, or secrets. | Schema forbids those types; test. |
| TPL-03 | Terminology overrides only change display labels; API field names and error codes are unaffected. | Contract test. |
| TPL-04 | Applying a template is idempotent: re-applying produces no new policy versions when values are unchanged. | Test. |

## 3. Setup wizard (first run)

The wizard runs at `/setup` while `instance.setup_completed=false`. All other routes return 409 `setup_required` (web redirects to `/setup`). Setup is protected by a **one-time setup token** printed to the server log at first start and required in the URL (`/setup?token=…`) so a stranger who reaches a fresh public instance cannot claim it (SEC-14).

| Step | Screen | Inputs (all with defaults) | Server effect |
|---|---|---|---|
| 0 | Token gate | setup token | validates `setup_token` (constant-time compare); 3 wrong tries → 429 for `auth.lockout.duration` |
| 1 | **Template** | template (default `personal`); language (`en`/`id`, default from browser `Accept-Language`, else `en`); public base URL (default: origin of the request) | none yet (held in the wizard state on the server, keyed by the token) |
| 2 | **Modules** | toggles prefilled from the template; core locked | none yet |
| 3 | **Rules** | policy form for the template's presets (registration mode, timezone default for new accounts, scheduler window, AI provider) rendered from `GET /policies` metadata; plus **create the first account**: email, password (min length per policy), display name, timezone (default from browser, else UTC) | **Finish** executes one transaction: apply template, module switches, policy versions, create first account (`instance_admin` + `account_owner`), mark `setup_completed`, invalidate the setup token, audit `setup.completed` |
| — | Done | — | auto-login; land on Today with an empty-state call to create the first goal |

| ID | Requirement | Acceptance |
|---|---|---|
| SET-01 | "Next, Next, Finish" works: with only email+password entered, setup completes using template defaults. | E2E journey J0. |
| SET-02 | Finish is atomic: any failure leaves `setup_completed=false` and no account, modules or policies applied. | Fault-injection test (kill the transaction at each statement). |
| SET-03 | Finish is idempotent per wizard session: a double-click or retry with the same `Idempotency-Key` returns the same result; a second finish after success returns 409 `setup_already_completed`. | Test. |
| SET-04 | The setup token is 128 bits, shown once in the server log (and via `kinevo admin setup-token` for operators), never stored in plaintext (hash only) and invalid after completion. | Test + log scan test (token never appears after first print). |
| SET-05 | Wizard state is server-side; reloading the browser resumes the same step. Abandoned wizard state expires after `KINEVO_SETUP_SESSION_TTL` (bootstrap environment variable, default 24 h). | Test. |
| SET-06 | For `docker run` demo use, `KINEVO_SETUP_EMAIL` and `KINEVO_SETUP_PASSWORD_FILE` may pre-create the first account non-interactively; this path still records the audit event and still requires module/template choice via `KINEVO_SETUP_TEMPLATE` (default `personal`). | Test. |

## 4. After setup

- Admin → Modules and Admin → Policies reuse the same endpoints (`PUT /admin/modules/{key}`, `PUT /policies/{key}`).
- Changing the template later does not exist; templates are only applied at setup or by `kinevo admin apply-template <id>` (which is idempotent, TPL-04).

## 5. Failure states and recovery

| Failure | User sees | Recovery |
|---|---|---|
| Wrong/missing token | "This setup link is not valid" (`setup_token_invalid`) with where to find the token | Re-read server log / run `kinevo admin setup-token` |
| Password below policy | Inline field error (`password_too_short`, `details.min`) | Fix input |
| Email already exists (cannot on fresh instance) | n/a | — |
| Database error at Finish | Banner "Setup could not be completed. Nothing was changed. Try again." (`service_unavailable`) | Retry (idempotent) |
| Browser offline | Banner "You are offline" with retry; no queue for setup | Reconnect |
