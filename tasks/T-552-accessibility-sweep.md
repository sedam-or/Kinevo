---
id: T-552
title: Complete the automated accessibility sweep and prepare the manual audit pack
milestone: M11
kind: feature
status: todo
depends_on: [T-550]
requirements: [TST-11, DS-A11Y-13, DS-A11Y-16]
branch: m11-t552-accessibility-sweep
budget_lines: 380
assignee: ""
---

# T-552: Complete the automated accessibility sweep and prepare the manual audit pack

## Goal

Every route and every catalogued UX state is checked automatically for WCAG 2.2 AA, forced-colors and 200 % zoom, and the manual audit is ready to run with an honest, unticked checklist.

## Context

> **TST-11** axe with tags `wcag2a, wcag2aa, wcag21aa, wcag22aa` on every route and every state in the UX catalogue (`standards/ux-states.md`); zero serious/critical violations; keyboard walk tests for dialogs and the command palette. **DS-A11Y-16** a manual audit before 1.0: keyboard-only pass, screen-reader pass on Today, Goals, Schedule and Notes, 200 % text zoom, high-contrast/forced-colors mode (system colours honoured via the `forced-colors` media query), recorded as a checklist.
> **Automated part (this task)**: a route inventory generated from the router (`web/scripts/routes.ts`) so a new route without an a11y test fails; a state sweep that renders each catalogued state through Storybook-free fixtures (MSW-driven) and runs axe; Playwright projects with `forcedColors: 'active'`, `reducedMotion`, and a 200 % zoom viewport (320 px wide equivalent) asserting no horizontal scroll and no clipped controls on the core screens; keyboard-walk specs for every dialog/sheet and the command palette (focus trap, Escape, return focus). Fix violations found in the owning feature files with minimal diffs (list every file touched in the Report).
> **Manual part (cannot be done by an agent)**: create `docs/a11y/audit-1.0.md` from a template containing the DS-A11Y-16 checklist with **every box unticked**, the environment fields (screen reader, browser, OS) blank, and a prominent line that it is completed by a human. The Report must not claim the audit is done; the 1.0 gate "Accessibility" stays open until the maintainer records it.

## Scope

**In:**
- `web/scripts/routes.ts`, `web/tests/e2e/gates/{a11y-all-routes,a11y-states,forced-colors,zoom-reflow,keyboard-dialogs}.spec.ts`, fixes in feature files as needed, `docs/a11y/audit-1.0.md`, `just a11y` recipe.

**Out:**
- Screen-reader testing, real devices.

## Files

| Path | Action | Notes |
|---|---|---|
| `web/scripts/routes.ts` | create |  |
| `web/tests/e2e/gates/*.spec.ts` | create |  |
| `docs/a11y/audit-1.0.md` | create | unticked template |
| `web/src/**` | modify | only minimal accessibility fixes, listed in the Report |
| `justfile` | modify | `a11y` recipe |

## Interfaces

```text
just a11y     # runs the Playwright a11y projects (environment-limited without browsers)
```

## Business rules

1. A route without an a11y spec fails the inventory test.
2. No rule is disabled to make a test pass; a justified exclusion (for example the Excalidraw canvas surface) is documented in the spec file and the Report.
3. The manual audit stays unticked.

## Security

- No new dependencies except `@axe-core/playwright` if not yet present (stack map).

## Implementation notes

- If a violation needs a design token change, run `python3 scripts/contrast.py` and update `tokens.json` per T-044 rules.

## Acceptance

| # | Given / When / Then | Test name |
|---|---|---|
| AC-1 | **Given** the router **When** the inventory runs **Then** every route has an a11y spec | `a11y_inventory::every_route_has_an_a11y_test` |
| AC-2 | **Given** every catalogued state **When** rendered **Then** axe reports no serious or critical violations | `e2e::tst_11_states_sweep` |
| AC-3 | **Given** forced-colors active **When** core screens render **Then** controls remain visible and focus rings present | `e2e::ds_a11y_forced_colors` |
| AC-4 | **Given** 200 % zoom at 320 px **When** core screens render **Then** no horizontal scroll and no clipped controls | `e2e::ds_a11y_zoom_reflow` |
| AC-5 | **Given** each dialog and the palette **When** walked by keyboard **Then** focus is trapped, Escape closes and focus returns | `e2e::ds_a11y_keyboard_dialogs` |
| AC-6 | **Given** the audit template **When** read **Then** every box is unticked and the human-completion notice exists | `a11y_inventory::manual_audit_template_is_unticked` |

## Tests

```
pnpm --dir web test
just a11y   # environment-limited without browsers
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
