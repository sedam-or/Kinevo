---
id: T-036
title: Implement the setup token, InstanceState, the setup gate and the wizard session endpoints
milestone: M1
kind: feature
status: todo
depends_on: [T-034, T-035, T-017, T-016, T-012]
requirements: [ADM-FR-01, ADM-FR-02, SET-02, SET-04, SET-05, SEC-AC-08, SEC-14, D-42]
branch: m1-t036-admin-setup-gate-and-session
budget_lines: 400
assignee: ""
---

# T-036: Implement the setup token, InstanceState, the setup gate and the wizard session endpoints

## Goal

A fresh instance prints a one-time setup token, answers 409 `setup_required` everywhere except the setup routes, and lets the holder of the token walk the wizard in server-side memory until Finish.

## Context

> **ADM-FR-01** (`spec/modules/admin.md`): before `setup_completed`, every route except `/setup/*`, `/livez`, `/readyz` returns 409 `setup_required`; after completion `/setup/*` returns 409 `setup_already_completed`.
> **SET-04** (`spec/05-templates-and-setup.md`): the setup token is 128 bits, shown once in the server log (and via `kinevo admin setup-token`), stored only as a hash, invalid after completion. **SET-05**: wizard state lives in bounded process memory (D-42); reload resumes the step; abandoned state expires after `KINEVO_SETUP_SESSION_TTL` (default 24 h); a restart discards wizard state but the token stays valid and the wizard restarts at step 1.
> **SEC-AC-08**: a stranger guessing at `/setup/session` gets 429 after 3 tries; no account is created; audit `setup.token_failed`. Constant-time compare (SEC-14).
> **Endpoints** (`admin.md` §2): `GET /setup/status` → `{ setup_completed, templates, locales }`; `POST /setup/session {token}` → 201 `{ session_id, step: 1 }` (`setup_token_invalid` 422, `rate_limited` 429, `setup_already_completed` 409); `GET /setup/session/{id}`; `PUT /setup/session/{id} { step, template_id?, locale?, base_url?, modules?, policies? }` → 200 state (validated, nothing applied; `template_invalid`, `module_dependency_unmet`, `policy_value_invalid`, `policy_unknown`).
> **SM-20**: `created → step 2 → step 3 → finished`; back navigation allowed; `finished` and `expired` terminal; one `finished` per instance.
> **D-42**: the attempt counter and wizard sessions are process memory (`moka`, bounded, TTL).
> **Validation**: `base_url` kind `https_url` (https, or http on loopback; no path beyond `/`, no credentials; trailing slash removed).

## Scope

**In:**
- First-start logic: if `instance_settings.setup_completed = false` and `setup_token_hash` is null, generate a 128-bit token with `TokenGen`, store its hash, print the full URL line once to the log; `rotate_setup_token` function for the CLI.
- `InstanceState` port implementation (reads `setup_completed` with a short cache, flips on completion).
- Setup gate wiring: T-017's setup-gate middleware calls `InstanceState`.
- `GET /setup/status`, `POST /setup/session`, `GET`/`PUT /setup/session/{id}`.
- `WizardStore` over `moka` (max 8 sessions, TTL from env), attempt counter 3 tries per source key with lockout `auth.lockout.duration`.

**Out:**
- Finish: T-037. The wizard UI: T-050. Reading `KINEVO_SETUP_SESSION_TTL` from the environment: T-039 passes it in.

## Files

| Path | Action | Notes |
|---|---|---|
| `crates/kinevo-mod-admin/src/app/setup.rs` | create | token bootstrap, session logic |
| `crates/kinevo-mod-admin/src/infra/wizard_store.rs` | create | `moka` store, attempt counter |
| `crates/kinevo-mod-admin/src/infra/instance_state.rs` | create | `InstanceState` impl |
| `crates/kinevo-mod-admin/src/http/setup.rs` | create | 4 handlers, route specs (Access::Public) |
| `crates/kinevo-mod-admin/tests/setup_session.rs` | create | AC-1…AC-8 |

## Interfaces

```rust
pub struct SetupService { /* db, wizard: WizardStore, tokens, clock, audit */ }
impl SetupService {
    pub async fn ensure_token(&self) -> Result<Option<Secret<String>>, AppError>;   // Some only on first generation
    pub async fn open_session(&self, source: &str, token: &Secret<String>) -> Result<WizardState, AppError>;
    pub async fn update(&self, id: SessionId, patch: WizardPatch) -> Result<WizardState, AppError>;
}
pub struct WizardState { pub session_id: SessionId, pub step: u8, pub template_id: String, pub locale: String, pub base_url: Option<String>,
                         pub modules: BTreeMap<String, bool>, pub policies: BTreeMap<String, serde_json::Value> }
```

## Business rules

1. Token compare is constant-time on hashes; a wrong token increments the source's counter and writes `setup.token_failed` (no token material in `details`).
2. After 3 wrong tries the source key is locked for `auth.lockout.duration`; the response is 429 `rate_limited`.
3. `PUT` validates every field against T-035/T-033 rules and stores the result; defaults are filled from the chosen template; nothing touches the database tables except the audit row for failures.
4. Session ids are 128-bit random tokens; unknown or expired ids give `not_found`.
5. After `setup_completed`, every setup route returns 409 `setup_already_completed`.

## Security

- The token is printed once at first generation (SET-04); a log scan test proves it never appears afterwards, including in request logs and errors.
- The wizard store is bounded (8 sessions, oldest evicted) so a stranger cannot exhaust memory.
- Setup routes are `Access::Public` but rate-limited; they exist only while `setup_completed = false`.

## Implementation notes

- Source key for the counter is the client IP from the trusted-proxy rule of T-018, falling back to a constant when absent.
- Use the injected clock for TTL and lockout; tests advance `FixedClock`.
- `ensure_token` runs from `kinevo serve` startup (T-039).

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a fresh database **When** `ensure_token` runs twice **Then** the first returns a token and stores only its hash; the second returns `None` | `setup_session::set_04_token_printed_once_hash_stored` |
| AC-2 | **Given** `setup_completed=false` **When** any non-setup route is called **Then** 409 `setup_required`; `/livez` and `/setup/status` work | `setup_session::adm_fr_01_gate_before_setup` |
| AC-3 | **Given** the right token **When** `POST /setup/session` **Then** 201 with step 1; `GET` returns the state | `setup_session::open_session_ok` |
| AC-4 | **Given** three wrong tokens **When** a fourth attempt (even correct) arrives **Then** 429; audit has `setup.token_failed` x3; no account exists | `setup_session::sec_ac_08_three_tries_then_429` |
| AC-5 | **Given** a session at step 2 **When** the process restarts (new store) **Then** the old id is `not_found`, the token still opens a new session at step 1 | `setup_session::set_05_restart_resets_wizard_keeps_token` |
| AC-6 | **Given** `FixedClock` advanced past the TTL **When** the session is read **Then** `not_found` | `setup_session::set_05_session_expires` |
| AC-7 | **Given** `PUT` with `ai` on and `goals` off **When** sent **Then** 422 `module_dependency_unmet`; a bad `base_url` gives a field error | `setup_session::put_validates_modules_and_url` |
| AC-8 | **Given** completed setup **When** `POST /setup/session` **Then** 409 `setup_already_completed` | `setup_session::adm_fr_01_setup_routes_closed_after` |

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
