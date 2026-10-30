---
id: T-051
title: Build the setup wizard UI (token gate, template, modules, rules and first account)
milestone: M1
kind: feature
status: review
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

### Result

Implemented the first-run setup wizard from token entry through the first account, including template, locale, base URL, module and policy forms, inline validation, retry-safe Finish, in-memory authentication, and navigation to Today. The backend returns policy and module form metadata from the existing registries. All eight named acceptance tests pass; `just check` did not finish cleanly because the shared Docker-backed PostgreSQL suite failed intermittently during database startup/cleanup under the full parallel run.

### Acceptance

| Criterion | Test | Result |
|---|---|---|
| AC-1 | `setup_ui::set_token_hidden_and_language_default` | PASS |
| AC-2 | `setup_ui::set_01_next_next_finish` | PASS |
| AC-3 | `setup_ui::sec_ac_08_lockout_message` | PASS |
| AC-4 | `setup_ui::module_dependency_inline` | PASS |
| AC-5 | `setup_ui::set_03_retry_reuses_key_and_banner` | PASS |
| AC-6 | `setup_ui::set_05_reload_resumes_step` | PASS |
| AC-7 | `setup_ui::password_policy_error_inline` | PASS |
| AC-8 | `setup_session::set_policy_form_in_wizard_state` | PASS |

### Commands and counts

- `pnpm --dir web test -- src/features/setup/SetupPage.test.tsx` before implementation: exit 1 as expected; `SetupPage` was unresolved and 0 tests ran.
- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-admin --test setup_session --features kinevo-db/testing set_policy_form_in_wizard_state` before implementation: exit 100 as expected; the new named assertion failed because policy form metadata was absent.
- `PATH=/usr/bin:$PATH COREPACK_ENABLE_PROJECT_SPEC=0 pnpm --dir web typecheck`: exit 0; Node 26.12.1 emitted the package's Node 26.13.0 engine warning.
- `PATH=/usr/bin:$PATH COREPACK_ENABLE_PROJECT_SPEC=0 pnpm --dir web lint`: final exit 0; Biome reported six existing CSS `!important` warnings and nine informational findings. The first unqualified `pnpm --dir web lint` exited 1 because Corepack's cached `pnpm/12.16.1/bin/pnpm.cjs` was absent; the local pnpm invocation was used thereafter. A lint retry caught untranslated language names and import ordering; both were fixed.
- `PATH=/usr/bin:$PATH COREPACK_ENABLE_PROJECT_SPEC=0 pnpm --dir web test`: exit 0; 32 files, 72 tests passed.
- `cargo nextest run -p kinevo-mod-identity -p kinevo-mod-admin --test descriptor`: exit 0; 5 passed.
- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-admin --test setup_session --features kinevo-db/testing`: exit 0; 9 passed, including AC-8.
- `cargo fmt --all`: exit 0.
- `eval "$(scripts/dev-postgres.sh env)" && cargo nextest run -p kinevo-mod-identity --test account --test-threads 1 api_60_patch_optimistic_concurrency`: exit 0; 1 passed on isolated retry after the workspace run reported PostgreSQL unavailable.
- `eval "$(scripts/dev-postgres.sh env)" && PATH="$HOME/.cargo/bin:/usr/bin:$PATH" COREPACK_ENABLE_PROJECT_SPEC=0 just check`: first post-fix run exited 1 after all 388 Rust tests (388 passed, 1 skipped), web tests, lint, cargo-deny and spec checks passed; the final generated-schema diff check detected the expected un-staged `web/src/api/schema.d.ts` update. The generated schema was then staged for the next run.
- The same `just check` command after staging the generated schema exited 100: nextest ran 286/388 tests, 285 passed and one failed at `crates/kinevo-mod-identity/tests/account.rs:165` with `disposable PostgreSQL test database should start: Unavailable`; 102 tests were not run, 1 test was skipped. The isolated retry above passed.
- A further run of the same `just check` command exited 100: 359/388 tests ran, 358 passed and one failed at `crates/kinevo-db/src/testing/mod.rs:537` with `test database cleanup failed`; 29 tests were not run and 1 was skipped. Eleven tests exceeded 60 seconds. PostgreSQL was accepting connections and the existing `postgres:17-alpine` container was reused; no image was built or pulled.
- `PATH="$HOME/.cargo/bin:/usr/bin:$PATH" COREPACK_ENABLE_PROJECT_SPEC=0 just spec`: exit 0; `spec_tool.py` found 0 issues in 60 spec files, contrast reported 0 failures, `tasks_index.py --check` reported 0 problems in 132 tasks, OpenAPI check passed, and regenerated API types matched the staged output.
- `python3 scripts/tasks_index.py && python3 scripts/tasks_index.py --check && python3 scripts/spec_tool.py check`: exit 0; both task-index checks reported 0 problems in 132 tasks and the spec checker reported 0 findings in 60 files.
- `git diff HEAD --check`: exit 0.

### Files changed

Application files (`git diff HEAD --numstat` at review): `contracts/openapi.json` +131/-0; `crates/kinevo-app/src/openapi.rs` +1/-0; `crates/kinevo-mod-admin/src/app/setup.rs` +138/-6; `crates/kinevo-mod-admin/src/http/openapi.rs` +3/-0; `crates/kinevo-mod-admin/src/http/setup.rs` +9/-2; `crates/kinevo-mod-admin/tests/setup_session.rs` +54/-4; `web/src/api/schema.d.ts` +38/-2; `web/src/app/router.tsx` +2/-8; `web/src/auth/AuthManager.ts` +4/-0; `web/src/i18n/en.json` +19/-0; `web/src/i18n/id.json` +19/-0. New setup files: `DonePage.tsx` 10 lines, `ModulesStep.tsx` 56, `RulesStep.tsx` 212, `SetupPage.test.tsx` 300, `SetupPage.tsx` 237, `TemplateStep.tsx` 63, `TokenStep.tsx` 37, `schemas.ts` 10, and `useWizard.ts` 307. `tasks/T-051-web-setup-wizard.md` and `tasks/RUN_REPORT.md` record task status and run evidence.

### Deviations

- **Resolved by implementer, pending architect review:** added `module_form` metadata (kind, label key, enabled state, and requirements) to the setup-session response. The unauthenticated wizard needs these existing registry values to render locked core switches and dependency messages; the prior boolean map did not carry them.
- `crates/kinevo-mod-admin/src/http/openapi.rs`, `crates/kinevo-app/src/openapi.rs`, `web/src/app/router.tsx`, `web/src/auth/AuthManager.ts`, and both locale catalogues are minimal wiring required to expose the response contract, register `/setup`, accept the returned token in memory, and render translated labels; these paths were not individually listed in Files.
- `budget_lines: 400` was exceeded; recorded under D-55 as guidance, not a blocker.
- The final full `just check` attempts remain environment-limited by the shared PostgreSQL test harness startup/cleanup failures described above. The focused setup suite, acceptance tests, web suite, descriptor tests, and `just spec` passed; no failed test is reported as passing.

### Spec issues noticed

No direct task/spec contradiction was found. The task interface did not describe how an unauthenticated module step obtains registry kind and dependency metadata; the additive `module_form` response resolves that gap pending architect review.

### Not done / not verified

- A clean end-to-end `just check` exit was not obtained because the full parallel PostgreSQL-backed workspace run failed during database startup/cleanup; see the exact commands and errors above.
- Browser E2E for the setup journey was not run; T-057 owns that journey. The seven UI acceptance tests ran in Vitest/jsdom.
- Firefox/WebKit browser execution was not attempted for this task.

### Follow-up suggestions

Review the additive `module_form` field and consider whether the shared PostgreSQL test harness should expose the underlying cleanup error instead of its current generic failure.

## Questions

- **Q-1 (resolved by implementer, pending architect review):** the unauthenticated setup wizard needs module kind and dependency metadata, while the session state previously exposed only enabled booleans. Added `module_form` from the compiled registry. Review whether this additive response shape should be retained.
