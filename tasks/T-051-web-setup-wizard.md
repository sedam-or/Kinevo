---
id: T-051
title: Build the setup wizard UI (token gate, template, modules, rules and first account)
milestone: M1
kind: feature
status: in_progress
depends_on: [T-050, T-037, T-035]
requirements: [SET-01, SET-02, SET-03, SET-05, SEC-AC-08, DS-A11Y-06, FE-04, UXS-02]
branch: m1-t051-web-setup-wizard
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-051: Build the setup wizard UI (token gate, template, modules, rules and first account)

## Goal

A first-time operator opens `/setup?token=…`, walks Next, Next, Finish and lands signed in on Today, with every failure state from the spec rendered.

## Context

> **Wizard** (`spec/05-templates-and-setup.md` §3): Step 0 token gate; Step 1 template (default `personal`), language (`en`/`id`, default from the browser), public base URL (default: the request origin); Step 2 modules (toggles prefilled from the template, core locked); Step 3 rules (policy form rendered from `GET /policies` metadata: registration mode, default timezone, scheduler window, AI provider) plus the first account (email, password, display name, timezone defaulted from the browser, else UTC); Finish; then auto-login and Today with the empty state.
> **Endpoints** (`modules/admin.md` §2): `GET /setup/status`, `POST /setup/session {token}`, `GET`/`PUT /setup/session/{id}` (`template_invalid`, `module_dependency_unmet`, `policy_value_invalid`, `policy_unknown`), `POST /setup/session/{id}/finish` with `Idempotency-Key` (422, `setup_already_completed`, `service_unavailable`). Policy metadata for the form comes from the setup session state because `GET /policies` needs authentication; this task adds `policy_form` (metadata of the template's instance-scope presets) to the wizard state returned by T-036's endpoints.
> **UX keys** (`ux-states.md` §3): `setup.token.invalid` (inline, Show how), `setup.step.template|modules|rules`, `setup.finish.error` (banner alert, Retry), `setup.done`, `state.setup.required`, `state.rate_limited`; failure table in `05` §5: wrong token, password below policy (`password_too_short` with `details.min`), DB error at Finish ("Nothing was changed. Try again."), offline (banner with retry, no queue).
> **SET-01** Next, Next, Finish works with only email and password. **SET-03** Finish is idempotent per wizard session (key kept across retries). **SET-05** reload resumes the same step from the server state; a restart returns to step 1 (the UI shows the token gate again if the session id is unknown). **DS-A11Y-06** never ask for the same information twice. **SEC-AC-08**: 429 after 3 wrong tokens shows the lockout message with the countdown.
> **Journey J0** (`standards/user-journeys.md`) is the E2E script (T-057).

## Scope

**In:**
- Routes `/setup` (reads `?token=`), step components, a `useWizard()` hook over the session endpoints with optimistic step navigation (back allowed), form schemas with zod, and `/setup` guard that shows the `setup_already_completed` page when applicable.
- Backend adjustment in `kinevo-mod-admin`: the `PUT /setup/session/{id}` and `GET` responses include `policy_form: [{key, type, bounds, label_key, value, scope}]` for the template's instance-scope presets and the first-account defaults, so the Rules step renders from metadata without an authenticated `GET /policies`; update T-036's DTO, OpenAPI file and its test.
- Finish handling: store the returned access token in `AuthManager` (memory), navigate to `/today`, show `setup.done` once.
- MSW handlers and component tests for each step and failure.

**Out:**
- The E2E journey run: T-057. Sample-data tick box (needs M2 modules): not shown in M1.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/features/setup/{SetupPage,TokenStep,TemplateStep,ModulesStep,RulesStep,DonePage}.tsx` | create | wizard |
| `web/src/features/setup/useWizard.ts, schemas.ts` | create | state and forms |
| `crates/kinevo-mod-admin/src/http/setup.rs, app/setup.rs` | modify | add `policy_form` to the wizard state DTO |
| `contracts/openapi.json` | modify | regenerated |
| `web/src/api/schema.d.ts` | modify | regenerated |
| `web/src/features/setup/*.test.tsx` | create | AC tests |
| `crates/kinevo-mod-admin/tests/setup_session.rs` | modify | one added test |

## Interfaces

```ts
type WizardState = { session_id: string; step: 1|2|3; template_id: string; locale: 'en'|'id'; base_url?: string;
                     modules: Record<string, boolean>; policy_form: PolicyField[] };
function useWizard(token: string): { state; setStep(n); patch(p); finish(account): Promise<void>; error?: AppError };
```

## Business rules

1. Default values are filled by the server; the UI only edits the diff the user changed.
2. Core module switches are rendered disabled and checked, with the visible label "On".
3. Finish disables only while submitting; a retry reuses the same `Idempotency-Key`.
4. Dependency errors are shown inline next to the switch with `admin.module.dependency`.
5. The language choice applies to the wizard immediately.

## Security

- The setup token is read from the URL once, moved to memory and removed from the address bar with `history.replaceState`.
- The token and password never appear in logs, error reports or query keys.
- No wizard data is stored in browser storage (the server holds it).

## Implementation notes

- Backend change is small: serialise fields the service already holds; add the AC-8 test in `setup_session.rs`.
- The wizard uses the same primitives as the rest of the app; no special styling beyond tokens.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** a valid token URL **When** opened **Then** the token leaves the address bar and step 1 shows with the browser language preselected | `setup_ui::set_token_hidden_and_language_default` |
| AC-2 | **Given** defaults **When** the user presses Next, Next and enters email and password then Finish **Then** the app lands on Today (SET-01) | `setup_ui::set_01_next_next_finish` |
| AC-3 | **Given** three wrong tokens **When** submitted **Then** the lockout message with countdown shows (SEC-AC-08) | `setup_ui::sec_ac_08_lockout_message` |
| AC-4 | **Given** `ai` on with `goals` off **When** the server answers `module_dependency_unmet` **Then** the inline message names the missing module | `setup_ui::module_dependency_inline` |
| AC-5 | **Given** a 503 at Finish **When** the user retries **Then** the same idempotency key is sent and the banner `setup.finish.error` clears on success | `setup_ui::set_03_retry_reuses_key_and_banner` |
| AC-6 | **Given** a reload at step 2 **When** the page loads **Then** step 2 shows with the chosen template | `setup_ui::set_05_reload_resumes_step` |
| AC-7 | **Given** a weak password **When** Finish returns `password_too_short` **Then** the field shows the minimum and nothing else changes | `setup_ui::password_policy_error_inline` |
| AC-8 | **Given** `PUT /setup/session/{id}` **When** answered **Then** `policy_form` lists the template's instance presets with metadata | `setup_session::set_policy_form_in_wizard_state` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor
just check
cargo nextest run -p kinevo-mod-admin --test setup_session --features kinevo-db/testing
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
