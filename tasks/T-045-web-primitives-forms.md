---
id: T-045
title: Build the form primitives and button set on React Aria Components
milestone: M1
kind: feature
status: review
depends_on: [T-044, T-005]
requirements: [CA-15, DS-A11Y-01, DS-A11Y-03, DS-A11Y-05, FE-04, FE-16]
branch: m1-t045-web-primitives-forms
budget_lines: 400
assignee: "OpenAI Codex"
---

# T-045: Build the form primitives and button set on React Aria Components

## Goal

Button, IconButton, Link, TextField, TextArea, NumberField, Select, Switch, Checkbox, RadioGroup and the form field wiring exist as tested, token-styled components with no business logic.

## Context

> **Component catalogue (§7, `spec/09-design-system.md`)**: all interactive primitives are built on React Aria Components, styled with tokens; no business logic (CA-15); each ships with a state test and an axe check.
> **Button**: primary, secondary, quiet, danger; all states of §5 (default, hover, focus-visible, pressed, disabled, loading, error, selected); loading shows spinner plus label, `aria-busy`; min target 44 px; one primary per view region; icon-only needs `aria-label`.
> **Fields**: label always visible (no placeholder-as-label), helper text, error text linked by `aria-describedby`, character counter when a limit applies; error shown with icon and text; `autocomplete` tokens set. **Switch/Checkbox/Radio**: 24 px visual, 44 px hit area; module toggles use Switch with a visible "On"/"Off" label.
> **DS-A11Y-03**: targets ≥ 24 px everywhere, primary controls 44 px. **DS-A11Y-05**: no cognitive tests; paste allowed in password fields; password managers supported; TOTP field `autocomplete="one-time-code"`.
> **FE-04**: forms use react-hook-form + zod schemas mirroring `standards/validation.md` kinds; server `errors[]` map onto fields all at once; submit disabled only while submitting.
> **Edges (§5)**: `shadow-*`, `edge-primary`, `pressed`, `inset-field` tokens give the tactile look.

## Scope

**In:**
- Components under `web/src/components/` with colocated tests and a `*.stories`-free demo route at `/__components` (dev only) for Playwright axe checks.
- `Field` composition (`FieldRow`, `FieldError`, `FieldHint`), `useFormErrors(serverErrors)` mapping `errors[]` onto react-hook-form fields.
- Validation helpers `zodKinds` for `email`, `short_text`, `key`, `https_url` and `iana_tz`, tested against the shared fixtures `spec/fixtures/validation/*.json` created by T-005. The password field has no fixture kind: its length bound comes from the `auth.password.min_length` policy and is enforced by the server (the client only mirrors it when the bound is known).

**Out:**
- Dialog, Toast, Banner and other overlays: T-046. Date/time pickers: M2 with their first consumer.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/src/components/{Button,IconButton,Link,TextField,TextArea,NumberField,Select,Switch,Checkbox,RadioGroup}.tsx` | create | one file each + test |
| `web/src/components/form/*.tsx` | create | Field wiring |
| `web/src/lib/validation/*.ts` | create | zod kinds on shared fixtures |
| `web/src/app/components-demo.tsx` | create | dev-only route |
| `web/tests/e2e/components.a11y.spec.ts` | create | axe on the demo |

## Interfaces

```tsx
<Button variant="primary" isLoading={bool} onPress={…}>Label</Button>
<TextField label="Email" type="email" autoComplete="email" error={fieldError} description="…" />
<Switch isSelected onChange label="Canvas" onLabel="On" offLabel="Off" />
export function useFormErrors(problem: AppError | undefined): { fieldErrors: Record<string,string>; formError?: string }
```

## Business rules

1. Every control renders all states of §5; disabled text keeps ≥ 3:1 contrast.
2. A field without a visible label is a type error (`label` is required).
3. Server field codes map to message keys `error.field.<code>` (catalogue arrives in T-047); until then the mapper returns the code and T-047 swaps in messages.
4. Zod kind schemas must accept and reject exactly the shared fixtures.

## Security

- Password fields allow paste and set `autocomplete` (`current-password`/`new-password`); no password reveal state is persisted.
- Components never log values.

## Implementation notes

- Use `react-aria-components` styling hooks (`data-*` attributes) with Tailwind variants bound to tokens.
- Keep each component under ~80 lines; shared tokens as Tailwind theme values.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** each Button variant **When** rendered in each state **Then** classes/aria reflect the state and loading keeps the label | `components::ds_button_states` |
| AC-2 | **Given** a TextField with an error **When** rendered **Then** the error text is linked by `aria-describedby` and has icon + text | `components::ds_field_error_linked` |
| AC-3 | **Given** a server problem with two field errors **When** mapped **Then** both land on their fields at once | `components::fe_04_server_errors_map_all_at_once` |
| AC-4 | **Given** the shared validation fixtures **When** run through the zod kinds **Then** all accept/reject cases agree | `components::tst_03_zod_matches_shared_fixtures` |
| AC-5 | **Given** the demo route **When** scanned by axe (wcag2a, wcag2aa, wcag21aa, wcag22aa) **Then** zero serious/critical violations | `components.a11y::tst_11_primitives_axe_clean` |
| AC-6 | **Given** primary controls **When** measured **Then** ≥ 44×44 px and all others ≥ 24×24 px | `components.a11y::ds_a11y_03_target_size` |

## Tests

```
pnpm --dir web typecheck
pnpm --dir web lint
pnpm --dir web test
just check
pnpm --dir web exec playwright test tests/e2e/components.a11y.spec.ts
```

## Definition of done

- [ ] Every acceptance criterion has its named test, and it passes (or is marked environment-limited with the exact command not run).
- [ ] `just check` passes locally.
- [ ] Only the files listed under *Files* changed; diff within budget.
- [ ] Report below is filled in.

## Report

_Filled in by the implementer (WORKFLOW.md §4)._

### Summary

The ten React Aria-based controls, shared field/error wiring, validation schemas, and development-only accessibility demo now exist and pass their tests. Server errors map to all corresponding fields, and shared validation fixtures match the client schemas.

### Acceptance results

| Criterion | Test name | Result |
|---|---|---|
| AC-1 | `components::ds_button_states` | pass |
| AC-2 | `components::ds_field_error_linked` | pass |
| AC-3 | `components::fe_04_server_errors_map_all_at_once` | pass |
| AC-4 | `components::tst_03_zod_matches_shared_fixtures` | pass |
| AC-5 | `components.a11y::tst_11_primitives_axe_clean` | pass in Chromium; other engines are environment-limited below |
| AC-6 | `components.a11y::ds_a11y_03_target_size` | pass in Chromium; other engines are environment-limited below |

### Commands run

| Exact command | Exit | Result |
|---|---:|---|
| `pnpm --dir web test --reporter=verbose` | 1 | Expected TDD red: newly named component tests failed because their modules had not yet been created; the Playwright spec was also initially discovered by Vitest and was excluded in the final config. |
| `pnpm --dir web typecheck` (default shell toolchain) | 1 | The Node 22 Corepack shim could not find `/home/kepolu/.cache/node/corepack/v1/pnpm/12.16.1/bin/pnpm.cjs`; rerun with the pinned Node 26/pnpm toolchain passed. |
| `export PATH="/tmp/kinevo-t043-bin:/tmp/kinevo-node-v26.13.0/node-v26.13.0-linux-x64/bin:$HOME/.cargo/bin:$PATH"; pnpm --dir web typecheck` | 0 | TypeScript check passed. |
| `pnpm --dir web lint` (first run) | 1 | Biome found formatting issues and rejected the direct control-character regex; formatting and a code-point predicate fixed them. |
| `export PATH="/tmp/kinevo-t043-bin:/tmp/kinevo-node-v26.13.0/node-v26.13.0-linux-x64/bin:$HOME/.cargo/bin:$PATH"; pnpm --dir web lint` | 0 | Passed with four reduced-motion `!important` warnings. |
| `eval "$(scripts/dev-postgres.sh env)"; just check` (first run) | 1 | Stopped in Biome because Playwright wrote unformatted `web/test-results/.last-run.json`; the output directory was moved to `/tmp` and the generated file removed. |
| `pnpm --dir web test -- src/lib/validation/zod-kinds.test.ts --reporter=verbose` | 1 | Regression test reproduced the root-vs-path trailing-slash bug: expected `https://example.test/path/`, received `https://example.test/path`. |
| `export PATH="/tmp/kinevo-t043-bin:/tmp/kinevo-node-v26.13.0/node-v26.13.0-linux-x64/bin:$HOME/.cargo/bin:$PATH"; pnpm --dir web exec vitest run src/lib/validation/zod-kinds.test.ts --reporter=verbose` | 0 | Both validation tests passed after the URL normalization fix. |
| `pnpm --dir web exec playwright test tests/e2e/components.a11y.spec.ts` | 1 | Chromium passed 2/2; Firefox and WebKit could not launch because of missing environment prerequisites listed below. |
| `pnpm --dir web exec playwright test tests/e2e/components.a11y.spec.ts --project=chromium` | 0 | Axe and target-size tests passed 2/2 in Chromium. |
| `export PATH="/tmp/kinevo-t043-bin:/tmp/kinevo-node-v26.13.0/node-v26.13.0-linux-x64/bin:$HOME/.cargo/bin:$PATH"; eval "$(scripts/dev-postgres.sh env)"; just check` | 0 | Full gate passed: Rust 385 passed / 1 skipped; web 22 passed; cargo-deny categories `ok`; spec check 0/60 findings; contrast 0 failures; task index 0/132 problems; OpenAPI drift clean. Cargo-deny reported duplicate-crate and unmatched-license-allowance warnings. |
| `python3 scripts/tasks_index.py && python3 scripts/tasks_index.py --check && python3 scripts/spec_tool.py check` | 0 | After setting T-045 to `review`: 0 task-index problems / 132 tasks and 0 spec findings / 60 files. |

### Files changed

Per-path added/removed counts are from the staged T-045 diff against the claim commit `22f4c64` on `dev`; `tasks/T-045-web-primitives-forms.md` includes this table.

| Path | Lines added / removed |
|---|---:|
| `spec/standards/open-source-stack.md` | +3 / −3 |
| `tasks/RUN_REPORT.md` | +146 / −0 |
| `tasks/T-045-web-primitives-forms.md` | +100 / −1 |
| `tasks/index.md` | +2 / −2 |
| `web/package.json` | +8 / −1 |
| `web/playwright.config.ts` | +20 / −0 |
| `web/pnpm-lock.yaml` | +557 / −18 |
| `web/src/app/components-demo.tsx` | +65 / −0 |
| `web/src/components/Button.test.tsx` | +57 / −0 |
| `web/src/components/Button.tsx` | +70 / −0 |
| `web/src/components/Checkbox.test.tsx` | +15 / −0 |
| `web/src/components/Checkbox.tsx` | +28 / −0 |
| `web/src/components/IconButton.test.tsx` | +11 / −0 |
| `web/src/components/IconButton.tsx` | +17 / −0 |
| `web/src/components/Link.test.tsx` | +24 / −0 |
| `web/src/components/Link.tsx` | +23 / −0 |
| `web/src/components/NumberField.test.tsx` | +17 / −0 |
| `web/src/components/NumberField.tsx` | +63 / −0 |
| `web/src/components/RadioGroup.test.tsx` | +24 / −0 |
| `web/src/components/RadioGroup.tsx` | +60 / −0 |
| `web/src/components/Select.test.tsx` | +25 / −0 |
| `web/src/components/Select.tsx` | +76 / −0 |
| `web/src/components/Switch.test.tsx` | +17 / −0 |
| `web/src/components/Switch.tsx` | +42 / −0 |
| `web/src/components/TextArea.test.tsx` | +11 / −0 |
| `web/src/components/TextArea.tsx` | +71 / −0 |
| `web/src/components/TextField.test.tsx` | +29 / −0 |
| `web/src/components/TextField.tsx` | +80 / −0 |
| `web/src/components/form/FieldRow.tsx` | +67 / −0 |
| `web/src/components/form/use-form-errors.test.tsx` | +35 / −0 |
| `web/src/components/form/use-form-errors.ts` | +17 / −0 |
| `web/src/lib/validation/zod-kinds.test.ts` | +43 / −0 |
| `web/src/lib/validation/zod-kinds.ts` | +140 / −0 |
| `web/src/main.tsx` | +6 / −3 |
| `web/src/styles/base.css` | +4 / −0 |
| `web/tests/e2e/components.a11y.spec.ts` | +28 / −0 |
| `web/tsconfig.json` | +8 / −1 |
| `web/vite.config.ts` | +2 / −1 |
| `web/vitest.config.ts` | +2 / −1 |

### Deviations

- `budget_lines: 400` was exceeded by approximately 1,469 added/removed lines excluding `web/pnpm-lock.yaml`; D-55 makes the budget guidance only.
- Added the task-required exact dependency versions to `spec/standards/open-source-stack.md` before installation, as required by CQ-16.
- Minimal wiring also changes the web package manifest/lockfile, Vite/Vitest/Playwright configuration, TypeScript includes, development entry point, and base stylesheet; Vitest excludes browser E2E specs and Playwright writes results under `/tmp` to keep generated artifacts out of the repository.
- React Aria filters `aria-busy`, so Button sets the required loading attribute through its DOM ref.
- The first Playwright webServer attempt exited early because its command included an extra `--`; the config was corrected and the exact test command rerun.

### Spec issues noticed

None affecting this task.

### Not done / not verified

- Firefox and WebKit runs remain environment-limited; the available Chromium project passed both acceptance checks.
- No browser binaries or system libraries were downloaded or installed.

### Follow-up suggestions

None.

## Questions

_None yet._
