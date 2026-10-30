---
id: T-045
title: Build the form primitives and button set on React Aria Components
milestone: M1
kind: feature
status: in_progress
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

## Questions

_None yet._
